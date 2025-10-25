<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Canvas Kosong Multi-Page — Drawpad dengan Thumbnail</title>
<style>
body { margin:0; font-family: Arial, sans-serif; background:#f5f5f5; display:flex; height:100vh; overflow:hidden; }
#sidebar { width:180px; background:#333; color:#fff; padding:10px; box-sizing:border-box; overflow-y:auto; }
#sidebar h3 { margin-top:0; font-size:16px; text-align:center; }
#thumbnails { display:flex; flex-direction:column; gap:8px; }
.thumbnail { width:100%; cursor:pointer; border:2px solid transparent; }
.thumbnail.active { border-color:#4caf50; }
#main { flex:1; display:flex; flex-direction:column; overflow:hidden; }
#toolbar {
  background:#222; color:#fff; padding:10px; display:flex; align-items:center; flex-wrap:wrap; gap:8px;
}
#toolbar select, #toolbar input, #toolbar button { padding:6px 10px; border-radius:5px; border:none; cursor:pointer; }
#container { flex:1; overflow:auto; background:#ddd; display:flex; flex-direction:column; align-items:center; padding:16px 0; }
.page { position:relative; margin:10px 0; background:#fff; box-shadow:0 0 6px rgba(0,0,0,.2); }
.page canvas { display:block; }
#progressBar { position:fixed; top:0; left:0; width:100%; height:6px; background:#333; display:none; z-index:100; }
#progressFill { height:100%; width:0; background:#4caf50; transition:width .15s; }
</style>
</head>
<body>

<div id="sidebar">
  <h3>Halaman</h3>
  <div id="thumbnails"></div>
</div>

<div id="main">
  <div id="progressBar"><div id="progressFill"></div></div>

  <div id="toolbar">
    <label>Alat:</label>
    <select id="toolSelect"><option value="brush">Brush</option><option value="eraser">Eraser</option></select>
    <label>Warna:</label>
    <input type="color" id="colorPicker" value="#ff0000">
    <label>Ukuran:</label>
    <input type="range" id="sizePicker" min="1" max="30" value="4">
    <button id="undoBtn">Undo</button>
    <button id="redoBtn">Redo</button>
    <button id="clearBtn">Hapus Coretan</button>
    <button id="addPageBtn">Tambah Halaman</button>
    <button id="saveServerBtn">Simpan ke Server (PDF)</button>
    <button id="savePdfBtn">Download PDF</button>
  </div>

  <div id="container"></div>
</div>

<script src="<?= base_url('assets/js/pdf-lib.min.js') ?>"></script>
<script>
// ======== VARIABEL GLOBAL ========
const container = document.getElementById('container');
const progressBar = document.getElementById('progressBar');
const progressFill = document.getElementById('progressFill');
const thumbnailsDiv = document.getElementById('thumbnails');

let tool='brush', color='#ff0000', size=4;
let pageStates=[], pageCanvases=[];

// ======== PROGRESS BAR ========
function showProgress(){progressBar.style.display='block'; progressFill.style.width='0%';}
function setProgress(p){progressFill.style.width=Math.max(0,Math.min(100,p))+'%';}
function hideProgress(){setTimeout(()=>{progressBar.style.display='none';},250);}

// ======== INISIALISASI HALAMAN & THUMBNAIL ========
function createBlankPage(width=800,height=1000){
  const pageDiv=document.createElement('div'); pageDiv.className='page'; pageDiv.style.width=width+'px'; pageDiv.style.height=height+'px';
  container.appendChild(pageDiv);

  const drawCanvas=document.createElement('canvas');
  drawCanvas.width=width; drawCanvas.height=height;
  drawCanvas.style.position='absolute'; drawCanvas.style.left='0'; drawCanvas.style.top='0';
  pageDiv.appendChild(drawCanvas);

  pageStates.push({dataURL:null, undoStack:[], redoStack:[]});
  pageCanvases.push(drawCanvas);
  enableDrawingOnCanvas(drawCanvas, pageStates.length-1);

  // Tambah thumbnail
  const thumb = document.createElement('canvas');
  thumb.width=160; thumb.height=200;
  thumb.className='thumbnail';
  updateThumbnail(thumb, drawCanvas);
  thumb.onclick=()=>{ drawCanvas.scrollIntoView({behavior:'smooth'}); setActiveThumbnail(thumb); };
  thumbnailsDiv.appendChild(thumb);
  setActiveThumbnail(thumb);
}

// Update thumbnail preview
function updateThumbnail(thumb, canvas){
  const ctx=thumb.getContext('2d');
  ctx.clearRect(0,0,thumb.width,thumb.height);
  ctx.drawImage(canvas,0,0,thumb.width,thumb.height);
}

// Set active thumbnail
function setActiveThumbnail(activeThumb){
  document.querySelectorAll('.thumbnail').forEach(t=>t.classList.remove('active'));
  if(activeThumb) activeThumb.classList.add('active');
}

// ======== DRAWING ========
document.getElementById('toolSelect').onchange = e=>tool=e.target.value;
document.getElementById('colorPicker').onchange = e=>color=e.target.value;
document.getElementById('sizePicker').oninput = e=>size=Number(e.target.value);

function enableDrawingOnCanvas(canvas,pageIndex){
  const ctx=canvas.getContext('2d'); ctx.lineJoin='round'; ctx.lineCap='round';
  let drawing=false,last={x:0,y:0};

  function getPos(e){ const rect=canvas.getBoundingClientRect(); const touch=e.touches?e.touches[0]:e; return {x:touch.clientX-rect.left,y:touch.clientY-rect.top}; }
  function pushState(){ try{ const snapshot=canvas.toDataURL(); pageStates[pageIndex].undoStack.push(snapshot); if(pageStates[pageIndex].undoStack.length>25) pageStates[pageIndex].undoStack.shift(); pageStates[pageIndex].redoStack=[]; pageStates[pageIndex].dataURL=snapshot; updateThumbnail(document.querySelectorAll('.thumbnail')[pageIndex], canvas);}catch{} }
  function doUndo(){ const state=pageStates[pageIndex]; if(!state||state.undoStack.length===0)return; const lastSnapshot=state.undoStack.pop(); state.redoStack.push(canvas.toDataURL()); const img=new Image(); img.src=lastSnapshot; img.onload=()=>{ ctx.clearRect(0,0,canvas.width,canvas.height); ctx.drawImage(img,0,0); state.dataURL=lastSnapshot; updateThumbnail(document.querySelectorAll('.thumbnail')[pageIndex], canvas); }; }
  function doRedo(){ const state=pageStates[pageIndex]; if(!state||state.redoStack.length===0)return; const snapshot=state.redoStack.pop(); state.undoStack.push(canvas.toDataURL()); const img=new Image(); img.src=snapshot; img.onload=()=>{ ctx.clearRect(0,0,canvas.width,canvas.height); ctx.drawImage(img,0,0); state.dataURL=snapshot; updateThumbnail(document.querySelectorAll('.thumbnail')[pageIndex], canvas); }; }

  canvas._doUndo=doUndo; canvas._doRedo=doRedo;

  const start=e=>{e.preventDefault(); drawing=true; last=getPos(e); pushState();};
  const move=e=>{if(!drawing)return;e.preventDefault();const pos=getPos(e);ctx.globalCompositeOperation=(tool==='eraser')?'destination-out':'source-over';ctx.strokeStyle=color;ctx.lineWidth=size;ctx.beginPath();ctx.moveTo(last.x,last.y);ctx.lineTo(pos.x,pos.y);ctx.stroke();last=pos;}
  const end=e=>{drawing=false; try{ pageStates[pageIndex].dataURL=canvas.toDataURL(); updateThumbnail(document.querySelectorAll('.thumbnail')[pageIndex], canvas);}catch{};}

  canvas.addEventListener('mousedown',start); canvas.addEventListener('mousemove',move);
  canvas.addEventListener('mouseup',end); canvas.addEventListener('mouseout',end);
  canvas.addEventListener('touchstart',start); canvas.addEventListener('touchmove',move); canvas.addEventListener('touchend',end);
}

// ======== UTILITY ========
function getFocusedDrawCanvas(){ const pages=Array.from(document.querySelectorAll('.page')); const containerRect=container.getBoundingClientRect(); let best=null,bestOverlap=-1; pages.forEach(p=>{ const rect=p.getBoundingClientRect(); const top=Math.max(rect.top,containerRect.top); const bottom=Math.min(rect.bottom,containerRect.bottom); const overlap=Math.max(0,bottom-top); if(overlap>bestOverlap){bestOverlap=overlap; best=p;} }); if(!best) return null; return best.querySelector('canvas'); }

document.getElementById('undoBtn').onclick=()=>{ const c=getFocusedDrawCanvas(); if(c && c._doUndo)c._doUndo(); };
document.getElementById('redoBtn').onclick=()=>{ const c=getFocusedDrawCanvas(); if(c && c._doRedo)c._doRedo(); };
document.getElementById('clearBtn').onclick=()=>{
  pageCanvases.forEach((c,i)=>{const ctx=c.getContext('2d');ctx.clearRect(0,0,c.width,c.height); pageStates[i].dataURL=null; pageStates[i].undoStack=[]; pageStates[i].redoStack=[]; updateThumbnail(document.querySelectorAll('.thumbnail')[i], c);});
  alert('✅ Semua coretan dihapus');
};

// ======== TAMBAH HALAMAN BARU ========
document.getElementById('addPageBtn').onclick=()=>{ createBlankPage(1200,1600); };

// ======== SIMPAN PDF ========
async function saveAsPdf(){
  showProgress(); setProgress(5);
  const pdfDoc=await PDFLib.PDFDocument.create();
  for(let i=0;i<pageStates.length;i++){
    const canvas=pageCanvases[i];
    const imgData=canvas.toDataURL('image/png');
    const page=pdfDoc.addPage([canvas.width,canvas.height]);
    const png=await pdfDoc.embedPng(imgData);
    page.drawImage(png,{x:0,y:0,width:canvas.width,height:canvas.height});
    setProgress(5+Math.round(((i+1)/pageStates.length)*90));
  }
  const bytes=await pdfDoc.save(); setProgress(100); hideProgress();
  const blob=new Blob([bytes],{type:'application/pdf'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='canvas_multi_page.pdf'; a.click();
}
document.getElementById('savePdfBtn').onclick=saveAsPdf;
document.getElementById('saveServerBtn').onclick=async()=>{
  try{showProgress(); setProgress(5);
    const pdfDoc=await PDFLib.PDFDocument.create();
    for(let i=0;i<pageStates.length;i++){
      const canvas=pageCanvases[i];
      const imgData=canvas.toDataURL('image/png');
      const page=pdfDoc.addPage([canvas.width,canvas.height]);
      const png=await pdfDoc.embedPng(imgData);
      page.drawImage(png,{x:0,y:0,width:canvas.width,height:canvas.height});
      setProgress(5+Math.round(((i+1)/pageStates.length)*80));
    }
    const bytes=await pdfDoc.save();
    const blob=new Blob([bytes],{type:'application/pdf'});
    const form=new FormData(); form.append('pdf_file',blob,'canvas_'+Date.now()+'.pdf');
    const res=await fetch("<?= base_url('index.php/document/save_pdf_server') ?>",{method:'POST',body:form});
    const json=await res.json(); setProgress(100); hideProgress();
    if(json.status==='success') alert('✅ PDF berhasil disimpan:\n'+json.file);
    else alert('❌ Gagal menyimpan PDF: '+(json.message||'unknown'));
  }catch(err){ hideProgress(); console.error(err); alert('⚠️ Terjadi kesalahan saat menyimpan');}
}

// ======== LOAD HALAMAN PERTAMA ========
createBlankPage(1200,1600);
</script>

</body>
</html>
