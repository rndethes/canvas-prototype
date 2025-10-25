<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Daftar Annotated PDF</title>
<style>
body {
  font-family: Arial, sans-serif; background: #f7f7f7;
  margin: 0; padding: 30px;
}
h2 { text-align: center; color: #333; }
.container {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 20px; margin-top: 20px;
}
.card {
  background: #fff; border-radius: 8px; box-shadow: 0 3px 6px rgba(0,0,0,0.1);
  padding: 15px; text-align: center;
}
.card img {
  max-width: 100%; border-radius: 5px; height: 300px; object-fit: contain;
}
a.btn {
  display: inline-block; margin-top: 10px; background: #007bff;
  color: white; padding: 8px 12px; border-radius: 5px; text-decoration: none;
}
a.btn:hover { background: #0056b3; }
</style>
</head>
<body>
<h2>📚 Daftar Hasil Annotated</h2>
<div class="container">
<?php if (empty($annotated_files)): ?>
  <p style="text-align:center;">Belum ada hasil anotasi.</p>
<?php else: ?>
  <?php foreach ($annotated_files as $file): ?>
    <div class="card">
      <img src="<?= base_url('uploads/annotated/'.$file) ?>" alt="Annotated">
      <div><?= $file ?></div>
      <a href="<?= base_url('index.php/document/open_annotated/'.$file) ?>" class="btn">Buka Ulang</a>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>
</body>
</html>
