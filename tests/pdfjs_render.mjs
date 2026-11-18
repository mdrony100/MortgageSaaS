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
 const page=await pdf.getPage(1);
 const textContent=await page.getTextContent();
 const text=textContent.items.map(x=>x.str).join(' ');
 if(!text.includes('FICTIONAL SAMPLE')||pdf.numPages!==1)throw Error('Invalid fictional source rendering');
 const positioned=textContent.items.filter(x=>x.str&&Array.isArray(x.transform));
 if(!positioned.length)throw Error('Text layer source has no positioned runs');
 let unitWidth=0;
 let rendered={width:0,height:0};
 for (const scale of [0.5,1.15,2]) {
  const viewport=page.getViewport({scale});
  if(!unitWidth)unitWidth=viewport.width/scale;
  else if(Math.abs(viewport.width/scale-unitWidth)>0.01)throw Error('Viewport scale is not linear');
  const canvas=createCanvas(Math.ceil(viewport.width),Math.ceil(viewport.height));
  await page.render({canvas,canvasContext:canvas.getContext('2d'),viewport}).promise;
  if(canvas.width!==Math.ceil(viewport.width)||canvas.height!==Math.ceil(viewport.height))throw Error('Canvas size does not follow the viewport');
  if(scale===1.15){
   rendered={width:canvas.width,height:canvas.height};
   if(process.env.PDF_RENDER_OUTPUT)fs.writeFileSync(process.env.PDF_RENDER_OUTPUT,canvas.toBuffer('image/png'));
  }
 }
 console.log(JSON.stringify({result:'passed',engine:'PDF.js '+pdfjs.version,pages:pdf.numPages,width:rendered.width,height:rendered.height,textRuns:positioned.length}));
}finally{await task.destroy()}
