<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Document extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->helper(array('form', 'url', 'file'));
    }

    //Halaman upload PDF
    public function upload() {
        $this->load->view('upload_pdf');
    }

    //Proses upload PDF
    public function upload_action() {
        $config['upload_path']   = './uploads/documents/';
        $config['allowed_types'] = 'pdf';
        $config['max_size']      = 10240;
        $config['encrypt_name']  = TRUE;

        $this->load->library('upload', $config);

        if (!$this->upload->do_upload('pdf_file')) {
            $error = array('error' => $this->upload->display_errors());
            $this->load->view('upload_pdf', $error);
        } else {
            $data = $this->upload->data();
            redirect('document/konva/' . $data['file_name']);
        }
    }

    // Editor Konva
    public function konva($filename = null) {
        if ($filename == null) redirect('document/upload');
        $data['filename'] = $filename;
        $this->load->view('document_konva', $data);
    }

    //PDF Viewer Proxy (agar PDF.js bisa render)
    public function view_pdf($filename) {
        $path = FCPATH . 'uploads/documents/' . $filename;

        if (!file_exists($path)) {
            show_404();
            return;
        }

    
        while (ob_get_level()) ob_end_clean();

        //Header lengkap untuk PDF.js
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Range');
        header('Accept-Ranges: bytes');
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($path));

        //Kirim data PDF mentah
        $fp = fopen($path, 'rb');
        fpassthru($fp);
        fclose($fp);
        exit;
    }

    //Simpan hasil coretan ke server
    public function save_canvas() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['image'])) {
            echo json_encode(['status' => 'error', 'message' => 'Data kosong']);
            return;
        }

        $folder = FCPATH . 'uploads/annotated/';
        if (!file_exists($folder)) mkdir($folder, 0777, true);

        $imgData = base64_decode(str_replace('data:image/png;base64,', '', $data['image']));
        $filename = 'annotated_' . time() . '.png';
        file_put_contents($folder . $filename, $imgData);

        echo json_encode([
            'status' => 'success',
            'file' => base_url('uploads/annotated/' . $filename)
        ]);
    }


    
    // Simpan hasil annotated (PDF langsung)
public function save_pdf_server() {
    if (empty($_FILES['pdf_file']['tmp_name'])) {
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada file PDF diterima']);
        return;
    }


    $folder = FCPATH . 'uploads/annotated/';
    if (!file_exists($folder)) mkdir($folder, 0777, true);

    $newName = 'annotated_' . time() . '.pdf';
    $target = $folder . $newName;

    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target)) {
        echo json_encode([
            'status' => 'success',
            'file' => base_url('uploads/annotated/' . $newName)
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file PDF']);
    }
}

}
