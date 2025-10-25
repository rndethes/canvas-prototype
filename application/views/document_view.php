<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>PDF Annotate - <?= htmlentities($document['title'] ?? $document['file_name']) ?></title>
  <style>
    body { font-family: Arial; margin: 0; }
    .container { display: flex; padding: 20px; gap: 20px; }
    #viewer { flex: 1; overflow: auto; height: 85vh; border: 1px solid #ddd; padding: 10px; position: relative; }
    .page-wrap { position: relative; margin-bottom: 24px; display: inline-block; }
    canvas.pdf-page { display: block; max-width: 100%; }
    .overlay { position: absolute; left: 0; top: 0; pointer-events: none; }
    .highlight { position: absolute; background: rgba(255,235,59,0.45); border: 1px solid rgba(255,200,0,0.8); cursor: pointer; }
    .toolbar { width: 320px; }
    .note-popup { position: fixed; background: #fff; border: 1px solid #ccc; padding: 10px; z-index: 9999; }
    .btn { display: inline-block; padding: 8px 12px; background: #2d8cf0; color: #fff; border-radius: 6px; text-decoration: none; margin: 6px 4px; cursor: pointer; border: none; }
  </style>
</head>
<body>

<div style="padding:12px;background:#111;color:#fff;">
  <h3 style="margin:0">Document: <?= htmlentities($document['file_name']) ?></h3>
</div>

<div class="container">
  <div id="viewer"></div>

  <div class="toolbar">
    <h4>Tools</h4>
    <button id="btn-highlight" class="btn">Highlight</button>
    <button id="btn-clear-temp" class="btn" style="background:#666">Cancel</button>
    <hr>
    <button id="btn-export" class="btn" style="background:#28a745">Export & Download</button>
    <button id="btn-upload" class="btn" style="background:#17a2b8">Upload Annotated</button>
    <hr>
    <h5>Saved notes</h5>
    <div id="notes-list" style="max-height:60vh; overflow:auto"></div>
  </div>
</div>

<!-- Popup catatan -->
<div id="note-modal" class="note-popup" style="display:none">
  <div><strong>Catatan</strong></div>
  <textarea id="note-text" rows="4" style="width:260px"></textarea>
  <div style="text-align:right;margin-top:6px">
    <button id="note-save" class="btn" style="background:#28a745">Simpan</button>
    <button id="note-cancel" class="btn" style="background:#999">Batal</button>
  </div>
</div>

<!-- Library PDF.js dan pdf-lib -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.8.162/pdf.min.js"></script>
<script src="https://unpkg.com/pdf-lib/dist/pdf-lib.min.js"></script>

<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.8.162/pdf.worker.min.js';

const documentId = <?= (int)$document['id'] ?>;
const pdfUrl = "<?= $document['file_url'] ?>";
const savedAnnotations = <?= json_encode($annotations) ?>;

const viewer = document.getElementById('viewer');
let pdfDoc = null, scale = 1.3, pageCanvases = [], overlayContainers = [];
let currentTool = null, dragState = null;
let annotations = savedAnnotations || [];

async function loadPdf() {
  const loadingTask = pdfjsLib.getDocument(pdfUrl);
  pdfDoc = await loadingTask.promise;
  for (let i = 1; i <= pdfDoc.numPages; i++) renderPage(i);
}

async function renderPage(pageNumber) {
  const page = await pdfDoc.getPage(pageNumber);
  const viewport = page.getViewport({ scale });
  const canvas = document.createElement('canvas');
  canvas.className = 'pdf-page';
  canvas.width = viewport.width;
  canvas.height = viewport.height;
  const ctx = canvas.getContext('2d');
  await page.render({ canvasContext: ctx, viewport }).promise;

  const wrap = document.createElement('div');
  wrap.className = 'page-wrap';
  wrap.style.width = viewport.width + 'px';
  wrap.style.height = viewport.height + 'px';
  wrap.appendChild(canvas);

  const overlay = document.createElement('div');
  overlay.className = 'overlay';
  overlay.style.width = viewport.width + 'px';
  overlay.style.height = viewport.height + 'px';
  overlay.style.position = 'absolute';
  overlay.style.pointerEvents = 'auto';
  wrap.appendChild(overlay);

  viewer.appendChild(wrap);

  pageCanvases[pageNumber] = { canvas, viewport };
  overlayContainers[pageNumber] = overlay;

  overlay.addEventListener('mousedown', e => onMouseDown(e, pageNumber));
  window.addEventListener('mousemove', e => onMouseMove(e, pageNumber));
  window.addEventListener('mouseup', e => onMouseUp(e, pageNumber));

  renderAnnotationsForPage(pageNumber);
}

function clientToLocal(e, elem) {
  const rect = elem.getBoundingClientRect();
  const x = e.clientX - rect.left;
  const y = e.clientY - rect.top;
  return { x, y, w: rect.width, h: rect.height };
}

function onMouseDown(e, pageNumber) {
  if (currentTool !== 'highlight') return;
  const overlay = overlayContainers[pageNumber];
  const pos = clientToLocal(e, overlay);
  dragState = { page: pageNumber, startX: pos.x, startY: pos.y, el: null };
  const div = document.createElement('div');
  div.className = 'highlight temp';
  div.style.left = pos.x + 'px';
  div.style.top = pos.y + 'px';
  div.style.width = '0px';
  div.style.height = '0px';
  overlay.appendChild(div);
  dragState.el = div;
}

function onMouseMove(e, pageNumber) {
  if (!dragState || dragState.page !== pageNumber) return;
  const overlay = overlayContainers[pageNumber];
  const pos = clientToLocal(e, overlay);
  const x = Math.min(pos.x, dragState.startX);
  const y = Math.min(pos.y, dragState.startY);
  const w = Math.abs(pos.x - dragState.startX);
  const h = Math.abs(pos.y - dragState.startY);
  dragState.el.style.left = x + 'px';
  dragState.el.style.top = y + 'px';
  dragState.el.style.width = w + 'px';
  dragState.el.style.height = h + 'px';
}

function onMouseUp(e, pageNumber) {
  if (!dragState || dragState.page !== pageNumber) return;
  const overlay = overlayContainers[pageNumber];
  const pos = clientToLocal(e, overlay);
  const x = Math.min(pos.x, dragState.startX);
  const y = Math.min(pos.y, dragState.startY);
  const w = Math.abs(pos.x - dragState.startX);
  const h = Math.abs(pos.y - dragState.startY);

  if (w < 8 || h < 8) { dragState.el.remove(); dragState = null; return; }

  showNoteModal(e.clientX, e.clientY, async (noteText) => {
    const viewport = pageCanvases[pageNumber].viewport;
    const rx = x / viewport.width;
    const ry = y / viewport.height;
    const rw = w / viewport.width;
    const rh = h / viewport.height;

    const form = new FormData();
    form.append('document_id', documentId);
    form.append('page_number', pageNumber);
    form.append('x', rx);
    form.append('y', ry);
    form.append('width', rw);
    form.append('height', rh);
    form.append('note', noteText);
    form.append('color', '#ffeb3b');

    const resp = await fetch('<?= base_url("document/save_annotation") ?>', { method: 'POST', body: form });
    const j = await resp.json();
    if (j.success) {
      annotations.push({
        id: j.id,
        document_id: documentId,
        page_number: pageNumber,
        x: rx, y: ry, width: rw, height: rh, color: '#ffeb3b', note: noteText
      });
      renderAnnotationsForPage(pageNumber);
      refreshNotesList();
    } else alert('Gagal menyimpan');
  });

  dragState.el.remove();
  dragState = null;
}

function renderAnnotationsForPage(pageNumber) {
  const overlay = overlayContainers[pageNumber];
  if (!overlay) return;
  overlay.querySelectorAll('.highlight.saved').forEach(el => el.remove());
  const pageAnns = annotations.filter(a => parseInt(a.page_number) === parseInt(pageNumber));
  const viewport = pageCanvases[pageNumber].viewport;
  pageAnns.forEach(a => {
    const el = document.createElement('div');
    el.className = 'highlight saved';
    el.style.left = (a.x * viewport.width) + 'px';
    el.style.top = (a.y * viewport.height) + 'px';
    el.style.width = (a.width * viewport.width) + 'px';
    el.style.height = (a.height * viewport.height) + 'px';
    el.dataset.note = a.note;
    overlay.appendChild(el);
    el.addEventListener('click', ev => showNoteModal(ev.clientX, ev.clientY, null, a));
  });
}

const noteModal = document.getElementById('note-modal');
const noteText = document.getElementById('note-text');
let noteCallback = null;
function showNoteModal(cx, cy, callback, annotation) {
  noteCallback = callback;
  noteText.value = annotation ? annotation.note : '';
  noteModal.style.left = (cx + 8) + 'px';
  noteModal.style.top = (cy + 8) + 'px';
  noteModal.style.display = 'block';
  noteText.focus();
  document.getElementById('note-save').onclick = function() {
    noteModal.style.display = 'none';
    if (noteCallback) noteCallback(noteText.value);
  };
  document.getElementById('note-cancel').onclick = function() {
    noteModal.style.display = 'none';
  };
}

document.getElementById('btn-highlight').onclick = () => currentTool = 'highlight';
document.getElementById('btn-clear-temp').onclick = () => currentTool = null;

function refreshNotesList() {
  const list = document.getElementById('notes-list');
  list.innerHTML = '';
  annotations.forEach(a => {
    const el = document.createElement('div');
    el.style.borderBottom = '1px solid #eee';
    el.style.padding = '6px 4px';
    el.innerHTML = `<small>Hal ${a.page_number}</small><div>${a.note || '—'}</div>`;
    list.appendChild(el);
  });
}
refreshNotesList();

document.getElementById('btn-export').onclick = async () => {
  const arrayBuffer = await fetch(pdfUrl).then(r => r.arrayBuffer());
  const pdfDoc = await PDFLib.PDFDocument.load(arrayBuffer);
  const pages = pdfDoc.getPages();

  for (const a of annotations) {
    const pIndex = parseInt(a.page_number) - 1;
    const page = pages[pIndex];
    const { width, height } = page.getSize();
    // Konversi koordinat relatif ke koordinat absolut PDF (bottom-left)
    const x = a.x * width;
    const w = a.width * width;
    const h = a.height * height;
    const y = height - (a.y * height) - h;

    // Draw highlight rectangle
    page.drawRectangle({
      x, y, width: w, height: h,
      color: PDFLib.rgb(1, 0.92, 0.2),
      opacity: 0.35,
      borderColor: PDFLib.rgb(1, 0.8, 0),
      borderWidth: 0
    });

    // Draw note text
    if (a.note && a.note.trim().length > 0) {
      page.drawText(a.note, {
        x: x,
        y: Math.max(10, y - 12), // teks muncul di bawah highlight
        size: 10,
        maxWidth: 200
      });
    }
  }

  const newPdfBytes = await pdfDoc.save();
  const blob = new Blob([newPdfBytes], { type: 'application/pdf' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'annotated.pdf';
  a.click();
  URL.revokeObjectURL(url);
};

document.getElementById('btn-upload').onclick = async () => {
  const arrayBuffer = await fetch(pdfUrl).then(r => r.arrayBuffer());
  const pdfDoc = await PDFLib.PDFDocument.load(arrayBuffer);
  const pages = pdfDoc.getPages();
  for (const a of annotations) {
    const pIndex = parseInt(a.page_number) - 1;
    const page = pages[pIndex];
    const { width, height } = page.getSize();
    const x = a.x * width;
    const y = height - (a.y * height) - (a.height * height);
    const w = a.width * width;
    const h = a.height * height;
    page.drawRectangle({ x, y, width: w, height: h, color: PDFLib.rgb(1, 0.92, 0.2), opacity: 0.35 });
    if (a.note) page.drawText(a.note, { x, y: Math.min(height - 20, y + h + 6), size: 10 });
  }

  const newPdfBytes = await pdfDoc.save();
  const fd = new FormData();
  fd.append('file', new File([newPdfBytes], 'annotated.pdf', { type: 'application/pdf' }));
  fd.append('document_id', documentId);

  const resp = await fetch('<?= base_url("document/upload_annotated_pdf") ?>', { method: 'POST', body: fd });
  const j = await resp.json();
  alert(j.success ? 'Upload berhasil!' : 'Gagal upload');

  const savedAnnotations = <?= json_encode($annotations) ?>;
let annotations = savedAnnotations || [];

};

loadPdf();
async function loadPdf() {
  const loadingTask = pdfjsLib.getDocument(pdfUrl);
  pdfDoc = await loadingTask.promise;

  for (let i = 1; i <= pdfDoc.numPages; i++) {
    await renderPage(i);
  }

  // render ulang semua anotasi setelah semua halaman selesai dimuat
  annotations.forEach(a => renderAnnotationsForPage(a.page_number));
  refreshNotesList();
}

</script>
</body>
</html>
