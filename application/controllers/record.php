<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once('skylight.php');

class Record extends skylight {

    function Record() {
        // Initalise the parent
        parent::__construct();
    }

	public function index() {
        // No record ID, so go home
		redirect('/');
	}

    function _remap($id, $params = array()) {

        // Perform content negotiation on the record ID
        $id = $this->_conneg($id);

        if ((empty($id)) || ($id == 'index')) {
            // No record ID, so go home
		    redirect('/');
        }

        $recorddisplay = $this->config->item('skylight_recorddisplay');
        $metafields = $this->config->item('skylight_meta_fields');
        $display_thumbnail = $this->config->item('skylight_display_thumbnail');
        $link_bitstream = $this->config->item('skylight_link_bitstream');
        $thumbnail_field = str_replace('.','',$this->config->item('skylight_thumbnail_field'));
        $bitstream_field = str_replace('.','',$this->config->item('skylight_bitstream_field'));

        $title = $this->skylight_utilities->getField('Title');

        // GET RECORD
        // Solr query business moved to solr_client library
        $data = $this->solr_client->getRecord($id);

        // Check for a valid ID
        if ($data['result_count'] == 0) {
            $data['page_title'] = 'Invalid record identifier!';
            $this->view('header', $data);
            $this->view('div_main');
            $this->view('record_invalid');
            $this->view('div_main_end');
            $this->view('div_sidebar');
            $this->view('div_sidebar_end');
            $this->view('footer');
            return;
        }

        // Digital object proxy
        if(count($this->uri->segments) == 4) {
            $segments = $this->uri->segments;
            $seq = $segments[3];
            $filename = $segments[4];

            if(preg_match('/^\d+$/',$seq)) {
                $url = $this->config->item('skylight_objectproxy_url').$id.'/'.$seq.'/'.$filename;

                // Which part of the solr results array is the bitstream in? (bitstream or thumbnail)
                $check = getBitstreamsMimeType($data['solr'][$bitstream_field], $seq);
                $filearray = $data['solr'][$bitstream_field];
                if (empty($check)) {
                    $check = getBitstreamsMimeType($data['solr'][$thumbnail_field], $seq);
                    $filearray = $data['solr'][$thumbnail_field];
                }

                // Check for a valid bitstream - if none, show a 404
                if (empty($check)) {
                    show_404('File not found');
                }

                // Set the correct response headers
                header('Content-Type: ' . getBitstreamsMimeType($filearray, $seq));
                header('Content-MD5: ' . getBitstreamMD5($filearray, $seq));
                header('Content-Length: ' . getBitstreamLength($filearray, $seq));

                // Stream the file
                readfile($url);

                // Go no further
                die();
            }
        }

        // Bitstream fields and config
        $data['bitstream_field'] = $bitstream_field;
        $data['thumbnail_field'] = $thumbnail_field;

        $data['link_bitstream'] = $link_bitstream;
        $data['display_thumbnail'] = $display_thumbnail;

        $data['sharethis'] = $this->config->item('skylight_share_buttons');

        $data['page_title'] = $data['solr'][$title][0];
        $data['title_field'] = $title;

        $data['id'] = $id;

        if(array_key_exists('Author', $recorddisplay)) {
            $data['author_field'] = $recorddisplay['Author'];
        }
        else {
            $data['author_field'] = 'dccreator';
        }
        
        $data['date_field'] = $this->skylight_utilities->getField('Date');

        // Send the display options config value for this collection
        $data['recorddisplay'] = $recorddisplay;
        $data['metafields'] = $metafields;

        $this->view('header', $data);
        $this->view('div_main');
        $this->view('record', $data);
        $this->view('div_main_end');
        $this->view('div_sidebar');
        $this->view('related_items', $data);
        $this->view('div_sidebar_end');

        $this->view('footer');
    }
}