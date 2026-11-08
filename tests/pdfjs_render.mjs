// Test the actual pinned PDF.js renderer against the PHP-generated fictional source.
// This is not browser automation or a substitute for visual/browser acceptance.
import {execFileSync} from 'node:child_process';
import fs from 'node:fs';
import {createCanvas} from '@napi-rs/canvas';
import * as pdfjs from 'pdfjs-dist/legacy/build/pdf.mjs';
const data=new Uint8Array(execFileSync('php',['-r',"require 'vendor/autoload.php'; echo App\\Services\\SampleData::pdf();"]));
const task=pdfjs.getDocument({data,useSystemFonts:true,disableFontFace:true});
try {
 const pdf=await task.promise;
 const page=await pdf.getPage(1);const viewport=page.getViewport({scale:1.15});
 const canvas=createCanvas(Math.ceil(viewport.width),Math.ceil(viewport.height));
 await page.render({canvas,canvasContext:canvas.getContext('2d'),viewport}).promise;
 const text=(await page.getTextContent()).items.map(x=>x.str).join(' ');
 if(!text.includes('FICTIONAL SAMPLE')||pdf.numPages!==1)throw Error('Invalid fictional source rendering');
 if(process.env.PDF_RENDER_OUTPUT)fs.writeFileSync(process.env.PDF_RENDER_OUTPUT,canvas.toBuffer('image/png'));
 console.log(JSON.stringify({result:'passed',engine:'PDF.js '+pdfjs.version,pages:pdf.numPages,width:canvas.width,height:canvas.height}));
}finally{await task.destroy()}
