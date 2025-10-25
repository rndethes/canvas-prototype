<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Document_model extends CI_Model {

    public function get_document($id) {
        return $this->db->get_where('documents', ['id' => $id])->row_array();
    }

    // kembalikan semua annotation untuk satu dokumen (urut berdasarkan id / dibuat)
    public function get_annotations($document_id) {
        $this->db->order_by('id', 'ASC');
        return $this->db->get_where('document_annotations', ['document_id' => $document_id])->result_array();
    }

    public function insert_annotation($data) {
        $this->db->insert('document_annotations', $data);
        return $this->db->insert_id();
    }

    public function update_annotation($id, $data) {
        $this->db->where('id', $id);
        return $this->db->update('document_annotations', $data);
    }

    public function delete_annotation($id) {
        $this->db->where('id', $id);
        return $this->db->delete('document_annotations');
    }

    public function save_annotated_file($data) {
        $this->db->insert('annotated_files', $data);
        return $this->db->insert_id();
    }
}
