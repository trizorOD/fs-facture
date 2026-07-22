<?php



namespace Finespirits\FsFacture;



use Mpdf\Mpdf;



if (!defined('ABSPATH')) {

    exit;

}



use Finespirits\FsFacture\AbstractClassFSFacture;



class PDF_FSFacture extends AbstractClassFSFacture {

    public function __construct() {

        $this->init();

    }

    public function init() {

        add_action('admin_post_generate_pdf', array($this, 'my_generate_pdf'));

    }



    public function my_generate_pdf() {

        if($this->get('facture_id')) {

            $facture = get_post($this->get('facture_id'));
            $facture_corrective_status = false;
            $document_name_main = '';

            if($facture) {

                $facture_year = date( 'Y', strtotime( $facture->post_date ) );

                $facture_date = date( 'Y-m-d H:i', strtotime( $facture->post_date ) );

                $document_name = $facture->ID."/".$facture_year.".pdf";

                $cloned_acf_data = get_post_meta($facture->ID, 'cloned_acf_data', true);

                $data = get_field('facture_group', $facture->ID);
                $general_group = get_data_arr('general_group', $data);

                $post_id = $facture->ID;

                if(check_data_arr('id_facture', $general_group)) {
                    $post_id = $general_group['id_facture'];
                }

                $document_name = $post_id."/".$facture_year;
                $document_file_name = $document_name.".pdf";

                $copyright = '';

                if(
                    $facture->post_status === 'facture_corrective' &&
                    !empty($cloned_acf_data)
                ) {
                    $facture_corrective_status = true;
                    if(
                        check_data_arr('general_group', $cloned_acf_data) &&
                        check_data_arr('id_facture', $cloned_acf_data['general_group'])
                    ) {
                        $document_name_main = $cloned_acf_data['general_group']['id_facture']."/".$facture_year;
                    }
                }



                if(!empty($data)) {

                    ob_start();

                    include __DIR__ . '/../templates/facture_pdf.php';

                    $html = ob_get_clean();



                    $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();

                    $fontDirs = $defaultConfig['fontDir'];



                    $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();

                    $fontData = $defaultFontConfig['fontdata'];



                    $mpdf = new Mpdf([

                        'fontDir' => array_merge($fontDirs, [

                            __DIR__ . '/../assets/fonts',

                        ]),

                        'fontdata' => $fontData + [

                            'roboto' => [

                                'R' => 'Roboto-Regular.ttf',

                                'B' => 'Roboto-Bold.ttf'

                            ]

                        ],

                        'default_font' => 'roboto',

                        'margin_left' => 8,

                        'margin_right' => 8, 

                        'margin_top' => 2,

                        'margin_bottom' => 10,

                        'margin_footer' => 2

                    ]);



                    if(

                        check_data_arr('general_group', $data) &&

                        check_data_arr('general_copyright', $data['general_group'])

                    ) {

                        $copyright = $data['general_group']['general_copyright'];

                    }



                    $mpdf->SetHTMLFooter('

                        <table>

                            <tr>

                                <td align="left" style="font-size: 8pt;">'.$facture_date.' '.$copyright.'</td>

                                <td align="right" style="font-size: 8pt;">'.__('Strona', "fs-facture").' {PAGENO} '.__('z', "fs-facture").' {nbpg}</td>

                            </tr>

                        </table>

                    ');



                    $mpdf->WriteHTML($html);



                    if($this->get('view') && $this->get('view') === 'yes') {

                        $mpdf->Output($document_file_name, \Mpdf\Output\Destination::INLINE);

                    }



                    if($this->get('download') && $this->get('download') === 'yes') {

                        $mpdf->Output($document_file_name, \Mpdf\Output\Destination::DOWNLOAD);

                    }

                } else echo "Not Facture's data";  



                

            } else echo "Not found Facture"; 

        } else echo "Not found Facture";

        



        exit();

    }



    public function download_pdf_button_render() {

        global $post;

        $url_clone = add_query_arg([
            'action'     => 'clone_facture_post',
            'post'       => $post->ID,
            '_wpnonce'   => wp_create_nonce('clone_facture_post')
        ], admin_url('admin.php'));

        if($this->get('post')) {

            $url     = admin_url('admin-post.php?action=generate_pdf');
            $url_xml = admin_url('admin-post.php?action=generate_ksef_xml');

            echo "<div class='pdf-buttons-download'>";

                echo '<p><a href="' . esc_url($url) . '&view=yes&facture_id='.$this->get('post').'" target="_blank" class="button button-primary">View Facture</a></p>';

                echo '<p><a href="' . esc_url($url) . '&download=yes&facture_id='.$this->get('post').'" class="button button-primary">Download Facture</a></p>';

                echo '<p><a href="' . esc_url($url_xml) . '&facture_id='.$this->get('post').'" class="button button-secondary">Download KSeF XML</a></p>';

                if($post->post_status === 'facture_current' || $post->post_status === 'publish') {
                    echo '<p><a href="' . esc_url($url_clone) . '&download=yes&facture_id='.$this->get('post').'" class="button button-primary" target="_blank">Corrective Facture</a></p>';
                }

            echo "</div>";

        }

    } 

}