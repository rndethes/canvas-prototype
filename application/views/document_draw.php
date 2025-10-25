<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Canvas PDF Drawing</title>
<style>
  body { margin: 0; font-family: Arial, sans-serif; background: #f5f5f5; }
  #toolbar {
    background: #222; color: white; padding: 10px;
    display: flex; align-items: center; gap: 10px;
  }
  #toolbar input, #toolbar button { cursor: pointer; }
  #viewer-container {
    position: relative;
    width: 100%;
    height: calc(100vh - 60px);
    overflow: auto;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    background: #ccc;
  }
  #pdf-viewer { position: relative; display: inline-block; }
  canvas { display: block; }
  #draw-layer {
    position: absolute;
    top: 0; left: 0;
    z-index: 10;
    pointer-events: auto;
  }
</style>
</head>
<body>
<div id="toolbar">
  <label>Warna:</label>
  <input type="color" id="colorPicker" value="#ff0000">
  <label>Ukuran:</label>
  <input type="range" id="sizePicker" min="1" max="10" value="3">
  <button id="clearBtn">🧹 Hapus Coretan</button>
  <button id="savePdfBtn">📄 Download PDF</button>
  <button id="saveJpgBtn">🖼️ Download JPG</button>
</div>

<div id="viewer-container">
  <div id="pdf-viewer">
    <canvas id="pdf-canvas"></canvas>
    <canvas id="draw-layer"></canvas>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.8.162/pdf.min.js"></script>
<script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>

<script>
pdfjsLib.GlobalWorkerOptions.workerSrc =
  'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.8.162/pdf.worker.min.js';

const pdfUrl = "<?= base_url('uploads/documents/contoh.pdf') ?>"; // ubah sesuai file kamu
let pdfDoc = null;
let scale = 1.2;
const pdfCanvas = document.getElementById("pdf-canvas");
const drawLayer = document.getElementById("draw-layer");
const pdfCtx = pdfCanvas.getContext("2d");
const drawCtx = drawLayer.getContext("2d");

// Setting drawing
let isDrawing = false;
let drawColor = "#ff0000";
let drawSize = 3;

// ========== LOAD PDF ==========
async function loadPDF() {
  const loadingTask = pdfjsLib.getDocument(pdfUrl);
  pdfDoc = await loadingTask.promise;
  const page = await pdfDoc.getPage(1);
  const viewport = page.getViewport({ scale });

  pdfCanvas.width = viewport.width;
  pdfCanvas.height = viewport.height;
  drawLayer.width = viewport.width;
  drawLayer.height = viewport.height;

  const renderContext = {
    canvasContext: pdfCtx,
    viewport: viewport,
  };
  await page.render(renderContext).promise;
}
loadPDF();

// ========== DRAW EVENT ==========
drawLayer.addEventListener("mousedown", (e) => {
  isDrawing = true;
  drawCtx.beginPath();
  drawCtx.moveTo(e.offsetX, e.offsetY);
});
drawLayer.addEventListener("mousemove", (e) => {
  if (!isDrawing) return;
  drawCtx.lineTo(e.offsetX, e.offsetY);
  drawCtx.strokeStyle = drawColor;
  drawCtx.lineWidth = drawSize;
  drawCtx.lineCap = "round";
  drawCtx.stroke();
});
drawLayer.addEventListener("mouseup", () => {
  isDrawing = false;
  drawCtx.closePath();
});
drawLayer.addEventListener("mouseleave", () => {
  if (isDrawing) { isDrawing = false; drawCtx.closePath(); }
});

// ========== TOOLBAR ==========
document.getElementById("colorPicker").addEventListener("input", e => drawColor = e.target.value);
document.getElementById("sizePicker").addEventListener("input", e => drawSize = e.target.value);
document.getElementById("clearBtn").addEventListener("click", () => {
  drawCtx.clearRect(0, 0, drawLayer.width, drawLayer.height);
});

// ========== GABUNG PDF + CORETA ==========
function mergeCanvas() {
  const merged = document.createElement("canvas");
  merged.width = pdfCanvas.width;
  merged.height = pdfCanvas.height;
  const mCtx = merged.getContext("2d");
  mCtx.drawImage(pdfCanvas, 0, 0);
  mCtx.drawImage(drawLayer, 0, 0);
  return merged;
}

// ========== DOWNLOAD JPG ==========
document.getElementById("saveJpgBtn").addEventListener("click", () => {
  const merged = mergeCanvas();
  const a = document.createElement("a");
  a.href = merged.toDataURL("image/jpeg");
  a.download = "annotated.jpg";
  a.click();
});

// ========== DOWNLOAD PDF ==========
document.getElementById("savePdfBtn").addEventListener("click", async () => {
  const merged = mergeCanvas();
  const imgData = merged.toDataURL("image/png");
  const pdfDocLib = await PDFLib.PDFDocument.create();
  const page = pdfDocLib.addPage([merged.width, merged.height]);
  const pngImage = await pdfDocLib.embedPng(imgData);
  page.drawImage(pngImage, {
    x: 0, y: 0, width: merged.width, height: merged.height
  });
  const pdfBytes = await pdfDocLib.save();
  const blob = new Blob([pdfBytes], { type: "application/pdf" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = "annotated.pdf";
  a.click();
});
</script>
</body>
</html>
