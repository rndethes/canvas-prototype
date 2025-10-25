<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>PDF Drawpad Konva — Fix Undo/Zoom Persist</title>
<style>
  body { margin:0; font-family: Arial, sans-serif; background:#f5f5f5; }
  #toolbar {
    background:#222; color:#fff; padding:10px;
    display:flex; align-items:center; flex-wrap:wrap; gap:8px;
    position:sticky; top:0; z-index:50;
  }
  #toolbar select, #toolbar input, #toolbar button {
    padding:6px 10px; border-radius:5px; border:none; cursor:pointer;
  }
  #container {
    width:100%; height:calc(100vh - 60px); overflow:auto; background:#ddd;
    display:flex; flex-direction:column; align-items:center; padding:16px 0;
  }
  .page { position:relative; margin:10px 0; background:#fff; box-shadow:0 0 6px rgba(0,0,0,.2); }
  .page canvas { display:block; }
  #progressBar { position:fixed; top:0; left:0; width:100%; height:6px; background:#333; display:none; z-index:100; }
  #progressFill { height:100%; width:0; background:#4caf50; transition:width .15s; }
</style>
</head>
<body>

<!-- Progress Bar (indikator proses simpan atau render) -->
<div id="progressBar"><div id="progressFill"></div></div>

<!-- Toolbar utama -->
<div id="toolbar">
  <label>Alat:</label>
  <select id="toolSelect"><option value="brush">Brush</option><option value="eraser">Eraser</option></select>
  <label>Warna:</label>
  <input type="color" id="colorPicker" value="#ff0000">
  <label>Ukuran:</label>
  <input type="range" id="sizePicker" min="1" max="30" value="4">
  <button id="undoBtn">Undo</button>
  <button id="redoBtn">Redo</button>
  <button id="zoomInBtn">Zoom +</button>
  <button id="zoomOutBtn">Zoom −</button>
  <button id="clearBtn">Hapus Coretan</button>
  <button id="saveServerBtn">Simpan ke Server (PDF)</button>
  <button id="savePdfBtn">Download PDF</button>
</div>

<div id="container"></div>

<!-- Memuat library PDF.js dan PDF-Lib -->
<script src="<?= base_url('assets/js/pdf.min.js') ?>"></script>
<script src="<?= base_url('assets/js/pdf-lib.min.js') ?>"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = "<?= base_url('assets/js/pdf.worker.min.js') ?>";
</script>

<script>
// ======== KONFIGURASI DASAR DAN VARIABEL GLOBAL ========
const pdfUrl = "<?= base_url('index.php/document/view_pdf/'.$filename) ?>";
const container = document.getElementById('container');
const progressBar = document.getElementById('progressBar');
const progressFill = document.getElementById('progressFill');

let tool = 'brush', color = '#ff0000', size = 4;
let scale = 1.5; // Skala awal tampilan PDF
const scaleStep = 0.2; // Langkah zoom in/out
const minScale = 0.8;
const maxScale = 3.0;

// Array pageStates untuk menyimpan data coretan dan stack undo/redo setiap halaman
let pageStates = [];
let pdfDoc = null;

// ======== EVENT HANDLER UNTUK TOOLBAR ========
document.getElementById('toolSelect').onchange = e => tool = e.target.value;
document.getElementById('colorPicker').onchange = e => color = e.target.value;
document.getElementById('sizePicker').oninput = e => size = Number(e.target.value);

// ======== FUNGSI PROGRESS BAR ========
function showProgress() { progressBar.style.display = 'block'; progressFill.style.width = '0%'; }
function setProgress(p) { progressFill.style.width = Math.max(0, Math.min(100, p)) + '%'; }
function hideProgress() { setTimeout(()=>{ progressBar.style.display='none'; }, 250); }

// ======== RENDER SEMUA HALAMAN PDF (DENGAN RESTORE CORETAN) ========
async function renderAllPages(restore=true) {
  showProgress(); setProgress(5);
  container.innerHTML = ''; // Bersihkan kontainer
  pageStates = pageStates || [];

  if (!pdfDoc) {
    const loading = pdfjsLib.getDocument({ url: pdfUrl });
    pdfDoc = await loading.promise;
  }
  setProgress(10);
  const num = pdfDoc.numPages;

  for (let i=1; i<=num; i++) {
    setProgress(10 + Math.floor((i/num)*70));
    const page = await pdfDoc.getPage(i);
    const viewport = page.getViewport({ scale });

    // Buat elemen div untuk setiap halaman
    const pageDiv = document.createElement('div');
    pageDiv.className = 'page';
    pageDiv.style.width = viewport.width + 'px';
    pageDiv.style.height = viewport.height + 'px';
    pageDiv.dataset.page = i;
    container.appendChild(pageDiv);

    // Canvas PDF (lapisan dasar dokumen)
    const pdfCanvas = document.createElement('canvas');
    pdfCanvas.width = viewport.width;
    pdfCanvas.height = viewport.height;
    pageDiv.appendChild(pdfCanvas);
    const ctx = pdfCanvas.getContext('2d', { willReadFrequently: true });
    await page.render({ canvasContext: ctx, viewport }).promise;

    // Canvas gambar (lapisan coretan)
    const drawCanvas = document.createElement('canvas');
    drawCanvas.classList.add('draw-layer');
    drawCanvas.width = viewport.width;
    drawCanvas.height = viewport.height;
    drawCanvas.style.position = 'absolute';
    drawCanvas.style.left = '0';
    drawCanvas.style.top = '0';
    pageDiv.appendChild(drawCanvas);

    // Inisialisasi data coretan halaman jika belum ada
    if (!pageStates[i-1]) {
      pageStates[i-1] = {
        dataURL: null,
        undoStack: [],
        redoStack: []
      };
    }

    // Kembalikan coretan yang sebelumnya disimpan
    if (restore && pageStates[i-1].dataURL) {
      const img = new Image();
      img.src = pageStates[i-1].dataURL;
      await new Promise(r => img.onload = r);
      const dctx = drawCanvas.getContext('2d');
      dctx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);
      const scaleX = drawCanvas.width / img.width;
      const scaleY = drawCanvas.height / img.height;
      dctx.save();
      dctx.scale(scaleX, scaleY);
      dctx.drawImage(img, 0, 0);
      dctx.restore();
    }

    // Aktifkan fungsi menggambar untuk setiap halaman
    enableDrawingOnCanvas(drawCanvas, i-1);
  }

  setProgress(95);
  hideProgress();
  setProgress(100);
}

// ======== FUNGSI MENGGAMBAR DENGAN FITUR UNDO/REDO PER HALAMAN ========
function enableDrawingOnCanvas(canvas, pageIndex) {
  const ctx = canvas.getContext('2d');
  ctx.lineJoin = 'round';
  ctx.lineCap = 'round';
  let drawing = false;
  let last = {x:0, y:0};

  // Pastikan setiap halaman memiliki struktur data undo/redo
  if (!pageStates[pageIndex]) pageStates[pageIndex] = { dataURL:null, undoStack:[], redoStack:[] };

  function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches ? e.touches[0] : e;
    return { x: (touch.clientX - rect.left), y: (touch.clientY - rect.top) };
  }

  // Simpan snapshot sebelum mulai menggambar
  function pushState() {
    try {
      const snapshot = canvas.toDataURL();
      pageStates[pageIndex].undoStack.push(snapshot);
      if (pageStates[pageIndex].undoStack.length > 25) pageStates[pageIndex].undoStack.shift();
      pageStates[pageIndex].redoStack = [];
      pageStates[pageIndex].dataURL = snapshot;
    } catch (err) {
      console.warn('Gagal menyimpan state', err);
    }
  }

  // Fungsi Undo
  function doUndo() {
    const state = pageStates[pageIndex];
    if (!state || state.undoStack.length === 0) return;
    try {
      const lastSnapshot = state.undoStack.pop();
      const current = canvas.toDataURL();
      state.redoStack.push(current);
      const img = new Image(); img.src = lastSnapshot;
      img.onload = () => {
        const dctx = canvas.getContext('2d');
        dctx.clearRect(0,0, canvas.width, canvas.height);
        dctx.drawImage(img, 0, 0);
        state.dataURL = lastSnapshot;
      };
    } catch (err) { console.warn('Undo gagal', err); }
  }

  // Fungsi Redo
  function doRedo() {
    const state = pageStates[pageIndex];
    if (!state || state.redoStack.length === 0) return;
    try {
      const snapshot = state.redoStack.pop();
      const current = canvas.toDataURL();
      state.undoStack.push(current);
      const img = new Image(); img.src = snapshot;
      img.onload = () => {
        const dctx = canvas.getContext('2d');
        dctx.clearRect(0,0, canvas.width, canvas.height);
        dctx.drawImage(img, 0, 0);
        state.dataURL = snapshot;
      };
    } catch (err) { console.warn('Redo gagal', err); }
  }

  // Hubungkan fungsi undo/redo ke elemen canvas
  canvas._doUndo = doUndo;
  canvas._doRedo = doRedo;

  // Event menggambar
  const start = e => { e.preventDefault(); drawing = true; last = getPos(e); pushState(); };
  const move = e => {
    if (!drawing) return;
    e.preventDefault();
    const pos = getPos(e);
    ctx.globalCompositeOperation = (tool === 'eraser') ? 'destination-out' : 'source-over';
    ctx.strokeStyle = color;
    ctx.lineWidth = size;
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    last = pos;
  };
  const end = e => { drawing = false; try { pageStates[pageIndex].dataURL = canvas.toDataURL(); } catch(e) {} };

  // Event mouse dan touch
  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  canvas.addEventListener('mouseup', end);
  canvas.addEventListener('mouseout', end);
  canvas.addEventListener('touchstart', start);
  canvas.addEventListener('touchmove', move);
  canvas.addEventListener('touchend', end);
}

// ======== UNDO / REDO DARI HALAMAN YANG SEDANG TERLIHAT ========
function getFocusedDrawCanvas() {
  const pages = Array.from(document.querySelectorAll('.page'));
  const containerRect = container.getBoundingClientRect();
  let best = null, bestOverlap = -1;
  for (const p of pages) {
    const rect = p.getBoundingClientRect();
    const top = Math.max(rect.top, containerRect.top);
    const bottom = Math.min(rect.bottom, containerRect.bottom);
    const overlap = Math.max(0, bottom - top);
    if (overlap > bestOverlap) { bestOverlap = overlap; best = p; }
  }
  if (!best) return null;
  return best.querySelector('canvas.draw-layer');
}

// Tombol Undo dan Redo
document.getElementById('undoBtn').onclick = () => {
  const c = getFocusedDrawCanvas();
  if (c && c._doUndo) c._doUndo();
};
document.getElementById('redoBtn').onclick = () => {
  const c = getFocusedDrawCanvas();
  if (c && c._doRedo) c._doRedo();
};

// ======== HAPUS SEMUA CORETAN ========
document.getElementById('clearBtn').onclick = () => {
  const all = document.querySelectorAll('canvas.draw-layer');
  all.forEach((c, idx) => {
    const ctx = c.getContext('2d');
    ctx.clearRect(0,0,c.width,c.height);
    if (pageStates[idx]) { pageStates[idx].dataURL = null; pageStates[idx].undoStack = []; pageStates[idx].redoStack = []; }
  });
  alert('✅ Semua coretan dihapus (dokumen tetap aman)');
};

// ======== FUNGSI ZOOM IN / OUT DENGAN RESTORE CORETAN ========
document.getElementById('zoomInBtn').onclick = async () => {
  if (scale + scaleStep > maxScale) return;
  captureAllDrawsToStates();
  scale = parseFloat((scale + scaleStep).toFixed(2));
  await renderAllPages(true);
};
document.getElementById('zoomOutBtn').onclick = async () => {
  if (scale - scaleStep < minScale) return;
  captureAllDrawsToStates();
  scale = parseFloat((scale - scaleStep).toFixed(2));
  await renderAllPages(true);
};

// Menyimpan coretan sementara sebelum zoom
function captureAllDrawsToStates() {
  const pages = document.querySelectorAll('.page');
  pages.forEach((p, idx) => {
    const draw = p.querySelector('canvas.draw-layer');
    if (!pageStates[idx]) pageStates[idx] = { dataURL:null, undoStack:[], redoStack:[] };
    try { pageStates[idx].dataURL = draw.toDataURL(); } catch(e) { console.warn('Gagal capture', e); }
  });
}

// ======== GENERATE PDF HASIL ANOTASI UNTUK SIMPAN / DOWNLOAD ========
async function generateAnnotatedPdfBytes(onProgress=null) {
  const pages = document.querySelectorAll('.page');
  const pdfDoc = await PDFLib.PDFDocument.create();
  for (let i=0; i<pages.length; i++) {
    const p = pages[i];
    const baseCanvas = p.querySelector('canvas:not(.draw-layer)');
    const drawCanvas = p.querySelector('canvas.draw-layer');
    const merged = document.createElement('canvas');
    merged.width = baseCanvas.width;
    merged.height = baseCanvas.height;
    const mctx = merged.getContext('2d');
    mctx.drawImage(baseCanvas, 0, 0);
    mctx.drawImage(drawCanvas, 0, 0);
    const imgData = merged.toDataURL('image/png');
    const page = pdfDoc.addPage([merged.width, merged.height]);
    const pngImage = await pdfDoc.embedPng(imgData);
    page.drawImage(pngImage, { x:0, y:0, width:merged.width, height:merged.height });
    if (onProgress) onProgress(Math.round(((i+1)/pages.length)*100));
  }
  return await pdfDoc.save();
}

// ======== DOWNLOAD PDF KE KOMPUTER ========
document.getElementById('savePdfBtn').onclick = async () => {
  showProgress();
  setProgress(5);
  const bytes = await generateAnnotatedPdfBytes((p)=>setProgress(5 + Math.round(p*0.8)));
  setProgress(95);
  const blob = new Blob([bytes], { type: 'application/pdf' });
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'annotated.pdf'; a.click();
  hideProgress();
};

// ======== SIMPAN PDF KE SERVER (DENGAN INDIKATOR PROGRESS) ========
document.getElementById('saveServerBtn').onclick = async () => {
  try {
    showProgress(); setProgress(5);
    const bytes = await generateAnnotatedPdfBytes((p)=>setProgress(5 + Math.round(p*0.8)));
    setProgress(80);
    const blob = new Blob([bytes], { type: 'application/pdf' });
    const form = new FormData();
    form.append('pdf_file', blob, 'annotated_' + Date.now() + '.pdf');

    const res = await fetch("<?= base_url('index.php/document/save_pdf_server') ?>", { method: 'POST', body: form });
    const json = await res.json();
    setProgress(100); hideProgress();
    if (json.status === 'success') {
      alert('✅ PDF berhasil disimpan di server:\n' + json.file);
    } else {
      alert('❌ Gagal menyimpan PDF: ' + (json.message || 'unknown'));
    }
  } catch (err) {
    hideProgress();
    console.error(err);
    alert('⚠️ Terjadi kesalahan saat menyimpan ke server. Lihat console.');
  }
};

// ======== LOAD PERTAMA (RENDER OTOMATIS SAAT HALAMAN DIBUKA) ========
(async ()=>{ 
  try { 
    const loading = pdfjsLib.getDocument({ url: pdfUrl }); 
    pdfDoc = await loading.promise; 
    await renderAllPages(false); 
  } catch(e){ 
    console.error(e); 
    alert('❌ Gagal memuat PDF. Cek console.'); 
  } 
})();
</script>

</body>
</html>
