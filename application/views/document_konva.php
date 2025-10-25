<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>PDF Drawpad Konva (Offline Version)</title>
<style>
  body { margin:0; font-family: Arial, sans-serif; background:#f5f5f5; }
  #toolbar {
    background:#222; color:#fff; padding:10px;
    display:flex; align-items:center; flex-wrap:wrap; gap:10px;
  }
  #toolbar select, #toolbar input, #toolbar button {
    padding:6px 10px; border-radius:5px; border:none;
  }
  #container {
    width:100%;
    height:calc(100vh - 60px);
    background:#ccc;
    display:flex;
    justify-content:center;
    align-items:flex-start;
    overflow:auto;
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
  <button id="clearBtn">🧹 Hapus Semua</button>
  <button id="saveServerBtn">💾 Simpan ke Server</button>
  <button id="savePdfBtn">📄 Download PDF</button>
  <button id="saveJpgBtn">🖼️ Download JPG</button>
</div>

<div id="container"></div>

<!-- ✅ 1️⃣ LOAD LIBRARY DARI LOKAL -->
<script src="<?= base_url('assets/js/konva.min.js') ?>"></script>
<script src="<?= base_url('assets/js/pdf.min.js') ?>"></script>
<script src="<?= base_url('assets/js/pdf-lib.min.js') ?>"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = "<?= base_url('assets/js/pdf.worker.min.js') ?>";
</script>

<!-- ✅ 2️⃣ SCRIPT UTAMA -->
<script>
if (typeof Konva === 'undefined') {
  alert('❌ Gagal memuat Konva.js! Pastikan file ada di folder assets/js/');
}

// file pdf dari server (lewat controller agar bebas CORS)
const pdfUrl = "<?= base_url('index.php/document/view_pdf/'.$filename) ?>";

// setup Konva stage dan layer
const container = document.getElementById('container');
const stage = new Konva.Stage({
  container: 'container',
  width: window.innerWidth,
  height: window.innerHeight - 60
});
const layer = new Konva.Layer();
stage.add(layer);

// ======== RENDER PDF KE BACKGROUND ========
async function renderPdfToCanvas() {
  console.log("🔍 Memuat PDF:", pdfUrl);
  try {
    const loadingTask = pdfjsLib.getDocument(pdfUrl);
    const pdf = await loadingTask.promise;
    const page = await pdf.getPage(1);

    const viewport = page.getViewport({ scale: 1.5 });
    stage.width(viewport.width);
    stage.height(viewport.height);

    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = viewport.width;
    tempCanvas.height = viewport.height;
    const ctx = tempCanvas.getContext('2d');

    const renderContext = { canvasContext: ctx, viewport: viewport };
    await page.render(renderContext).promise;

    const pdfImage = new Image();
    pdfImage.src = tempCanvas.toDataURL('image/png');
    pdfImage.onload = () => {
      const bg = new Konva.Image({
        image: pdfImage,
        x: 0, y: 0,
        width: viewport.width,
        height: viewport.height
      });
      layer.add(bg);
      bg.moveToBottom();
      layer.draw();
      console.log("✅ PDF berhasil dimuat di background");
    };
  } catch (error) {
    console.error("❌ Error saat memuat PDF:", error);
    alert("Gagal menampilkan PDF. Cek console (F12) untuk detail.");
  }
}
renderPdfToCanvas();

// ======== CANVAS UNTUK CORETAN ========
const canvas = document.createElement('canvas');
canvas.width = stage.width();
canvas.height = stage.height();
const image = new Konva.Image({ image: canvas, x:0, y:0 });
layer.add(image);

const ctx = canvas.getContext('2d');
ctx.lineJoin = 'round';
ctx.lineWidth = 4;
ctx.strokeStyle = '#ff0000';

let isPaint = false, lastPos = null, mode = 'brush';

function getPos() { return stage.getPointerPosition(); }

image.on('mousedown touchstart', () => { isPaint = true; lastPos = getPos(); });
stage.on('mouseup touchend', () => isPaint = false);
stage.on('mousemove touchmove', () => {
  if (!isPaint || !lastPos) return;
  const pos = getPos();
  ctx.globalCompositeOperation = (mode === 'eraser') ? 'destination-out' : 'source-over';
  ctx.beginPath();
  ctx.moveTo(lastPos.x, lastPos.y);
  ctx.lineTo(pos.x, pos.y);
  ctx.stroke();
  lastPos = pos;
  layer.batchDraw();
});

// ======== TOOLBAR ACTION ========
document.getElementById('toolSelect').onchange = e => mode = e.target.value;
document.getElementById('colorPicker').onchange = e => ctx.strokeStyle = e.target.value;
document.getElementById('sizePicker').oninput = e => ctx.lineWidth = e.target.value;
document.getElementById('clearBtn').onclick = () => {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  layer.draw();
};

// ======== MERGE CANVAS UNTUK SAVE ========
function mergeCanvas() {
  const merged = document.createElement('canvas');
  merged.width = stage.width();
  merged.height = stage.height();
  const mCtx = merged.getContext('2d');

  const bg = layer.children.find(obj => obj.className === 'Image');
  if (bg && bg.image()) mCtx.drawImage(bg.image(), 0, 0, merged.width, merged.height);
  mCtx.drawImage(canvas, 0, 0);
  return merged;
}

// ======== SIMPAN KE SERVER ========
document.getElementById('saveServerBtn').onclick = async () => {
  const merged = mergeCanvas();
  const imgData = merged.toDataURL('image/png');
  const response = await fetch("<?= base_url('index.php/document/save_canvas') ?>", {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ image: imgData })
  });
  const result = await response.json();
  if (result.status === 'success') {
    alert('✅ Berhasil disimpan di server:\n' + result.file);
  } else {
    alert('❌ Gagal menyimpan.');
  }
};

// ======== DOWNLOAD JPG ========
document.getElementById('saveJpgBtn').onclick = () => {
  const merged = mergeCanvas();
  const a = document.createElement('a');
  a.href = merged.toDataURL('image/jpeg');
  a.download = 'annotated.jpg';
  a.click();
};

// ======== DOWNLOAD PDF ========
document.getElementById('savePdfBtn').onclick = async () => {
  const merged = mergeCanvas();
  const imgData = merged.toDataURL('image/png');
  const pdfDoc = await PDFLib.PDFDocument.create();
  const page = pdfDoc.addPage([merged.width, merged.height]);
  const pngImage = await pdfDoc.embedPng(imgData);
  page.drawImage(pngImage, { x:0, y:0, width:merged.width, height:merged.height });
  const pdfBytes = await pdfDoc.save();
  const blob = new Blob([pdfBytes], { type:'application/pdf' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'annotated.pdf';
  a.click();
};
</script>
</body>
</html>
