<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>PDF Drawpad Per Halaman (Save as PDF)</title>
<style>
  body { margin:0; font-family: Arial, sans-serif; background:#f5f5f5; }
  #toolbar {
    background:#222; color:#fff; padding:10px;
    display:flex; align-items:center; flex-wrap:wrap; gap:10px;
    position:sticky; top:0; z-index:10;
  }
  #toolbar select, #toolbar input, #toolbar button {
    padding:6px 10px; border-radius:5px; border:none;
  }
  #container {
    width:100%;
    height:calc(100vh - 110px);
    background:#ccc;
    overflow:auto;
    scroll-behavior:smooth;
    display:flex;
    flex-direction:column;
    align-items:center;
  }
  .page {
    position:relative;
    margin:10px 0;
    background:#fff;
    box-shadow:0 0 6px rgba(0,0,0,0.3);
  }
  .page canvas {
    display:block;
  }
  canvas { touch-action:none; }
</style>
</head>
<body>
<div id="toolbar">
  <label>Alat:</label>
  <select id="toolSelect">
    <option value="brush">🖊️ Brush</option>
    <option value="eraser">🩹 Eraser</option>
  </select>
  <label>Warna:</label>
  <input type="color" id="colorPicker" value="#ff0000">
  <label>Ukuran:</label>
  <input type="range" id="sizePicker" min="1" max="15" value="4">
  <button id="clearBtn">🧹 Hapus Semua Coretan</button>
  <button id="saveServerBtn">💾 Simpan ke Server (PDF)</button>
  <button id="savePdfBtn">📄 Download PDF</button>
</div>

<div id="container"></div>

<script src="<?= base_url('assets/js/pdf.min.js') ?>"></script>
<script src="<?= base_url('assets/js/pdf-lib.min.js') ?>"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = "<?= base_url('assets/js/pdf.worker.min.js') ?>";
</script>

<script>
const pdfUrl = "<?= base_url('index.php/document/view_pdf/'.$filename) ?>";
const container = document.getElementById('container');

let tool = 'brush';
let strokeColor = '#ff0000';
let strokeSize = 4;
document.getElementById('toolSelect').onchange = e => tool = e.target.value;
document.getElementById('colorPicker').onchange = e => strokeColor = e.target.value;
document.getElementById('sizePicker').oninput = e => strokeSize = e.target.value;

// ======== RENDER PDF PER HALAMAN ========
async function renderPdf() {
  const loadingTask = pdfjsLib.getDocument(pdfUrl);
  const pdf = await loadingTask.promise;
  console.log("📄 Total halaman:", pdf.numPages);

  for (let i = 1; i <= pdf.numPages; i++) {
    const page = await pdf.getPage(i);
    const viewport = page.getViewport({ scale: 1.5 });

    const pageDiv = document.createElement('div');
    pageDiv.className = 'page';
    pageDiv.dataset.page = i;
    pageDiv.style.width = viewport.width + 'px';
    pageDiv.style.height = viewport.height + 'px';
    container.appendChild(pageDiv);

    // PDF background
    const pdfCanvas = document.createElement('canvas');
    pdfCanvas.width = viewport.width;
    pdfCanvas.height = viewport.height;
    pageDiv.appendChild(pdfCanvas);
    const ctx = pdfCanvas.getContext('2d');
    await page.render({ canvasContext: ctx, viewport }).promise;

    // Drawing layer
    const drawCanvas = document.createElement('canvas');
    drawCanvas.width = viewport.width;
    drawCanvas.height = viewport.height;
    drawCanvas.classList.add('draw-layer');
    drawCanvas.style.position = 'absolute';
    drawCanvas.style.left = 0;
    drawCanvas.style.top = 0;
    pageDiv.appendChild(drawCanvas);

    enableDrawing(drawCanvas);
  }
}
renderPdf();

// ======== MENGGAMBAR ========
function enableDrawing(canvas) {
  const ctx = canvas.getContext('2d');
  ctx.lineJoin = 'round';
  ctx.lineCap = 'round';
  ctx.lineWidth = strokeSize;
  ctx.strokeStyle = strokeColor;
  let drawing = false;
  let last = {x:0, y:0};

  function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches ? e.touches[0] : e;
    return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
  }

  const start = e => { drawing = true; last = getPos(e); };
  const move = e => {
    if (!drawing) return;
    const pos = getPos(e);
    ctx.lineWidth = strokeSize;
    ctx.strokeStyle = strokeColor;
    ctx.globalCompositeOperation = (tool === 'eraser') ? 'destination-out' : 'source-over';
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    last = pos;
  };
  const end = () => drawing = false;

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  canvas.addEventListener('mouseup', end);
  canvas.addEventListener('mouseout', end);
  canvas.addEventListener('touchstart', start);
  canvas.addEventListener('touchmove', e => { e.preventDefault(); move(e); });
  canvas.addEventListener('touchend', end);
}

// ======== HAPUS CORETAN SAJA ========
document.getElementById('clearBtn').onclick = () => {
  document.querySelectorAll('canvas.draw-layer').forEach(c => {
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, c.width, c.height);
  });
  alert("✅ Semua coretan dihapus (dokumen tetap aman)");
};

// ======== DOWNLOAD PDF KE KOMPUTER ========
document.getElementById('savePdfBtn').onclick = async () => {
  const pdfBytes = await generateAnnotatedPdf();
  const blob = new Blob([pdfBytes], { type:'application/pdf' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'annotated.pdf';
  a.click();
};

// ======== SIMPAN PDF KE SERVER ========
document.getElementById('saveServerBtn').onclick = async () => {
  const pdfBytes = await generateAnnotatedPdf();
  const blob = new Blob([pdfBytes], { type:'application/pdf' });
  const formData = new FormData();
  formData.append('pdf_file', blob, 'annotated.pdf');

  const response = await fetch("<?= base_url('index.php/document/save_pdf_server') ?>", {
    method: 'POST',
    body: formData
  });
  const result = await response.json();
  if (result.status === 'success') {
    alert('✅ PDF berhasil disimpan:\n' + result.file);
  } else {
    alert('❌ Gagal menyimpan PDF.');
  }
};

// ======== FUNGSI BUAT PDF ANNOTATED ========
async function generateAnnotatedPdf() {
  const pages = document.querySelectorAll('.page');
  const pdfDoc = await PDFLib.PDFDocument.create();

  for (const p of pages) {
    const pdfCanvas = p.querySelector('canvas:not(.draw-layer)');
    const drawCanvas = p.querySelector('.draw-layer');
    const merged = document.createElement('canvas');
    merged.width = pdfCanvas.width;
    merged.height = pdfCanvas.height;
    const ctx = merged.getContext('2d');
    ctx.drawImage(pdfCanvas, 0, 0);
    ctx.drawImage(drawCanvas, 0, 0);

    const imgData = merged.toDataURL('image/png');
    const page = pdfDoc.addPage([merged.width, merged.height]);
    const pngImage = await pdfDoc.embedPng(imgData);
    page.drawImage(pngImage, { x:0, y:0, width:merged.width, height:merged.height });
  }
  return await pdfDoc.save();
}
</script>
</body>
</html>
