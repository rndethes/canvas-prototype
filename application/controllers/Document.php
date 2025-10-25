<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Document extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->helper(array('form', 'url', 'file'));
    }

    // 🔹 Halaman upload PDF
    public function upload() {
        $this->load->view('upload_pdf');
    }

    // 🔹 Proses upload PDF
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

    // 🔹 Editor Konva untuk menggambar
    public function konva($filename = null) {
        if ($filename == null) redirect('document/upload');
        $data['filename'] = $filename;
        $this->load->view('document_konva', $data);
    }

    // 🔹 Proxy PDF agar bisa dibaca PDF.js
    public function view_pdf($filename) {
        $path = FCPATH . 'uploads/documents/' . $filename;
        if (file_exists($path)) {
            header('Content-Type: application/pdf');
            readfile($path);
        } else {
            show_404();
        }
    }

    // 🔹 Simpan hasil coretan (base64 image)
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

    // 🆕 🔹 Daftar hasil annotated
    public function list() {
        $path = FCPATH . 'uploads/annotated/';
        $files = glob($path . '*.png');
        $data['annotated_files'] = array_map('basename', $files);
        $this->load->view('annotated_list', $data);
    }

    // 🆕 🔹 Buka ulang hasil annotated di Konva
    public function open_annotated($filename = null) {
        if ($filename == null) redirect('document/list');
        $data['filename'] = $filename;
        $this->load->view('annotated_view', $data);
    }
}
