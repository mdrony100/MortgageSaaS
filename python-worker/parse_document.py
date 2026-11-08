"""No network, script execution, links or embedded files; derived artifacts only.
Use a separate unprivileged worker/container in production. OS limits are defense in depth,
not a claim that Python monkeypatching is a complete operating-system sandbox.
"""
import sys, os, json, resource, socket, hashlib, math, re
resource.setrlimit(resource.RLIMIT_CPU, (45, 45))
resource.setrlimit(resource.RLIMIT_AS, (1024*1024*1024,)*2)
resource.setrlimit(resource.RLIMIT_FSIZE, (24*1024*1024,)*2)
resource.setrlimit(resource.RLIMIT_NOFILE, (64,64))
os.umask(0o077)
def denied(*a, **kw): raise RuntimeError('Network is disabled')
socket.socket=denied
import pymupdf
from PIL import Image
Image.MAX_IMAGE_PIXELS=20000000

def parse(path, output, max_pages=120):
    if os.path.getsize(path)>20*1024*1024: raise ValueError('Document exceeds 20 MiB limit')
    os.makedirs(output,exist_ok=True)
    with open(path,'rb') as f: magic=f.read(8)
    image = magic.startswith(b'\x89PNG') or magic.startswith(b'\xff\xd8')
    if image:
        with Image.open(path) as im:
            im.verify()
        with Image.open(path) as im:
            if im.width*im.height>20000000: raise ValueError('Image exceeds pixel limit')
            im=im.convert('RGB'); im.thumbnail((2000,2000)); im.save(os.path.join(output,'page-1.png'))
            return {'format':'image','metadata':{},'structural':[], 'pages':[{'number':1,'width':im.width,'height':im.height,'text':'','words':[],'render':'page-1.png','sha256':hashlib.sha256(im.tobytes()).hexdigest()}]}
    if not magic.startswith(b'%PDF-'): raise ValueError('Malformed or unsupported document')
    doc=pymupdf.open(path)
    if doc.is_encrypted: raise ValueError('Password-protected PDF: upload an unlocked copy')
    if not 0 < len(doc) <= max_pages: raise ValueError('PDF page count outside configured limit')
    pages=[]; structural=[]
    if doc.is_repaired: structural.append('pdf_repaired_by_parser')
    # We inspect catalog strings; never execute JS, launch actions, attachments or URLs.
    for i in range(1,min(doc.xref_length(),10000)):
        obj=doc.xref_object(i,compressed=False)
        if any(x in obj for x in ['/JavaScript','/Launch','/EmbeddedFile','/OpenAction','/URI']):
            structural.append('embedded_action_or_external_reference'); break
    for i,p in enumerate(doc):
        if p.rect.width<=0 or p.rect.height<=0 or p.rect.width*p.rect.height>20000000: raise ValueError('PDF page dimensions exceed limit')
        scale=min(2,math.sqrt(4000000/(p.rect.width*p.rect.height)))
        p.get_pixmap(matrix=pymupdf.Matrix(scale,scale),alpha=False,annots=False).save(os.path.join(output,f'page-{i+1}.png'))
        text=p.get_text('text')
        if len(text)>500000: raise ValueError('Page text exceeds safe limit')
        words=[]
        for w in p.get_text('words'):
            rect=pymupdf.Rect(*w[:4])*p.rotation_matrix
            region=[rect.x0/p.rect.width,rect.y0/p.rect.height,rect.width/p.rect.width,rect.height/p.rect.height]
            x,y,width,height=region
            if all(math.isfinite(v) for v in region) and x>=0 and y>=0 and width>0 and height>0 and x+width<=1.000001 and y+height<=1.000001:
                words.append({'text':w[4],'region':region})
        fonts=sorted(set(span.get('font','unknown') for block in p.get_text('dict')['blocks'] if 'lines' in block for line in block['lines'] for span in line['spans']))
        declared=re.search(r'Page\s+(\d+)\s+(?:of|/)\s*(\d+)',text,re.I)
        pages.append({'fonts':fonts,'declared_number':int(declared[1]) if declared else None,'declared_total':int(declared[2]) if declared else None,'number':i+1,'width':p.rect.width,'height':p.rect.height,'text':text,'words':words,'render':f'page-{i+1}.png','sha256':hashlib.sha256(text.encode()+p.get_pixmap(matrix=pymupdf.Matrix(.25,.25),alpha=False,annots=False).samples).hexdigest()})
    return {'format':'pdf','metadata':{k:v for k,v in doc.metadata.items() if k in ['format','creator','producer','creationDate','modDate']},'structural':structural,'pages':pages}
if __name__=='__main__':
    try:
        result=parse(sys.argv[1],sys.argv[2],int(sys.argv[3]))
        with open(os.path.join(sys.argv[2],'manifest.json'),'x',encoding='utf-8') as f: json.dump(result,f)
        print(json.dumps({'manifest':'manifest.json'}))
    except Exception as e:
        # Do not print document content or paths to the parent/logs.
        known=str(e)
        safe=known if known in ['Document exceeds 20 MiB limit','Image exceeds pixel limit','Malformed or unsupported document','Password-protected PDF: upload an unlocked copy','PDF page count outside configured limit','PDF page dimensions exceed limit','Page text exceeds safe limit'] else 'Parser rejected document; upload a valid PDF, JPEG or PNG'
        print(json.dumps({'error':safe})); sys.exit(2)
