<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Lihat Annotated</title>
<style>
body { margin:0; background:#f5f5f5; font-family:Arial,sans-serif; }
#toolbar {
  background:#222; color:#fff; padding:10px; display:flex;
  align-items:center; justify-content:space-between;
}
#container { width:100%; height:calc(100vh - 60px); display:flex; justify-content:center; align-items:center; }
canvas { border:1px solid #ddd; }
a { color:white; text-decoration:none; }
</style>
</head>
<body>
<div id="toolbar">
  <div><a href="<?= base_url('index.php/document/list') ?>">⬅️ Kembali</a></div>
  <div>📄 <?= $filename ?></div>
</div>
<div id="container">
  <img src="<?= base_url('uploads/annotated/'.$filename) ?>" style="max-width:95%; max-height:95%; border-radius:8px;">
</div>
</body>
</html>
