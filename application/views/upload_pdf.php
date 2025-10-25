<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Upload PDF</title>
<style>
body {
  font-family: Arial, sans-serif;
  background: #f0f0f0;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
}
form {
  background: #fff;
  padding: 30px;
  border-radius: 10px;
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  width: 350px;
  text-align: center;
}
input[type=file] {
  margin: 10px 0;
  width: 100%;
}
button {
  background: #007bff;
  color: white;
  border: none;
  padding: 10px 15px;
  border-radius: 6px;
  cursor: pointer;
}
button:hover { background: #0056b3; }
.error { color: red; margin-top: 10px; }
</style>
</head>
<body>
<form action="<?= base_url('index.php/document/upload_action') ?>" method="post" enctype="multipart/form-data">
  <h2>Upload PDF Baru</h2>
  <input type="file" name="pdf_file" accept="application/pdf" required>
  <br><br>
  <button type="submit">Upload & Edit</button>

  <?php if(isset($error)): ?>
    <div class="error"><?= $error ?></div>
  <?php endif; ?>
</form>
</body>
</html>
