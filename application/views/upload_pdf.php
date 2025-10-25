<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Upload & Canvas</title>
<style>
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: 'Arial', sans-serif;
  background: #f0f2f5;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  padding: 20px;
}

.container {
  display: flex;
  gap: 30px;
  flex-wrap: wrap;
  justify-content: center;
  width: 100%;
  max-width: 900px;
}

.card {
  background: #ffffff;
  padding: 30px 25px;
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(0,0,0,0.1);
  flex: 1 1 300px;
  text-align: center;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 25px rgba(0,0,0,0.15);
}

.card h2 {
  margin-bottom: 20px;
  color: #333333;
  font-size: 1.5em;
}

input[type=file] {
  margin: 15px 0;
  width: 100%;
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 6px;
  cursor: pointer;
}

button {
  background: #007bff;
  color: white;
  border: none;
  padding: 12px 25px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1em;
  transition: background 0.2s ease, transform 0.2s ease;
}

button:hover {
  background: #0056b3;
  transform: scale(1.05);
}

.error {
  color: #e74c3c;
  margin-top: 15px;
  font-size: 0.95em;
}

@media (max-width: 768px) {
  .container {
    flex-direction: column;
    gap: 20px;
  }
}
</style>
</head>
<body>

<div class="container">

  <form class="card" action="<?= base_url('index.php/document/upload_action') ?>" method="post" enctype="multipart/form-data">
    <h2>Upload PDF Baru</h2>
    <input type="file" name="pdf_file" accept="application/pdf" required>
    <button type="submit">Upload & Edit</button>

    <?php if(isset($error)): ?>
      <div class="error"><?= $error ?></div>
    <?php endif; ?>
  </form>

  <form class="card" action="<?= base_url('index.php/document/canvas_blank') ?>" method="post" enctype="multipart/form-data">
    <h2>Mulai Canvas Kosong</h2>
    <button type="submit">Mulai</button>

    <?php if(isset($error)): ?>
      <div class="error"><?= $error ?></div>
    <?php endif; ?>
  </form>

</div>

</body>
</html>
