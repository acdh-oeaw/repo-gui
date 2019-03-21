<?php

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Routing;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\file\Entity;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use TCPDF;
use TCPDF_FONTS;

abstract class DepAgreeBaseForm extends FormBase
{
   
    /**
    * @var \Drupal\user\PrivateTempStoreFactory
    */
    protected $tempStoreFactory;

    /**
    * @var \Drupal\Core\Session\SessionManagerInterface
    */
    private $sessionManager;

    /**
    * @var \Drupal\Core\Session\AccountInterface
    */
    private $currentUser;

    /**
    * @var \Drupal\user\PrivateTempStore
    */
    protected $store;
   
    protected $formData = array();
    protected $dbData = array();
    protected $repoid = "";
    
    /**
   * Constructs a Multi step form Base.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   * @param \Drupal\Core\Session\AccountInterface $current_user
   */
    
    public function __construct(PrivateTempStoreFactory $temp_store_factory, SessionManagerInterface $session_manager, AccountInterface $current_user)
    {
        $this->tempStoreFactory = $temp_store_factory;
        $this->sessionManager = $session_manager;
        $this->currentUser = $current_user;
        $this->store = $this->tempStoreFactory->get('deep_agree_form_data');
    }
    
    public function getFormFromDB():array
    {
        $res = array();
        try {
            $query = db_select('oeaw_forms', 'of');
            $query->fields('of', array('data', 'userid', 'repoid'));
            $query->condition('of.userid', \Drupal::currentUser()->id());
            $query->condition('of.repoid', $this->repoid);
            $query->condition('of.status', 'open');
            $query->orderBy('of.date', 'DESC');
            $query->range(0, 1);
            $result = $query->execute()->fetchAssoc();
        } catch (Exception $ex) {
        }
        
        if ($result != false) {
            $res = $result;
        }
        
        return $res;
    }
    
    /**
     *
     * If the user is pasting a new form resource id then we need to change it
     * on the form
     *
     * @param string $repoFormID
     */
    public function checkRepoId(string $repoFormID)
    {
        if (empty($this->repoid)) {
            if (isset($repoFormID) && $repoFormID != "new") {
                $this->repoid = $repoFormID;
            } else {
                $this->store->set('material_acdh_repo_id', substr(md5(rand()), 0, 20));
                $this->repoid = $this->store->get('material_acdh_repo_id');
            }
        } else {
            //somebody trying to reach a diff one directly trough the url
            if ($this->repoid != $repoFormID) {
                $this->repoid = $repoFormID;
            }
        }
        
        if ($repoFormID == "new") {
            $this->store->set('material_acdh_repo_id', substr(md5(rand()), 0, 20));
            $this->repoid = $this->store->get('material_acdh_repo_id');
        }
    }
    
    public static function create(ContainerInterface $container)
    {
        return new static(
                $container->get('user.private_tempstore'),
                $container->get('session_manager'),
                $container->get('current_user')
        );
    }
    
    public function buildForm(array $form, FormStateInterface $form_state, $formid = null)
    {
        $repoFormID = \Drupal::routeMatch()->getParameter("formid");
        
        if (isset($repoFormID) && $repoFormID != "new") {
            $this->repoid = $repoFormID;
            $this->dbData = $this->getFormFromDB();
            
            if (count($this->dbData) == 0) {
                //return drupal_set_message($this->t('This REPO ID is already CLOSED!'), 'error');
                $msg = base64_encode("This REPO ID is already CLOSED! Or it is not yours!");
                $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
                $response->send();
                return;
            }
        } else {
            $this->store->set('material_acdh_repo_id', substr(md5(rand()), 0, 20));
            $this->repoid = $this->store->get('material_acdh_repo_id');
        }
     
        //start a manual session for anonymus user
        if (!isset($_SESSION['deep_agree_form_form_holds_session'])) {
            $_SESSION['deep_agree_form_form_holds_session'] = true;
            $this->sessionManager->start();
        }
        
        $form = array();
               
        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Submit'),
            '#button_type' => 'primary',
            '#weight' => 10,
            '#attributes' => array(
                'class' => array('btn'),
                'style' => 'margin:10px; color:white;'
            ),
        );

        return $form;
    }
    
    
    /*
     * Saves data from the multistep form
    */
    
    protected function saveData()
    {
        $form1 = $this->store->get('form1Val');
        $form2 = $this->store->get('form2Val');
        $form3 = $this->store->get('form3Val');
        //$form4 = $this->store->get('form4Val');
     
        $fileMetaData = $this->store->get('material_metadata_file');
        $fileNameScheme = $this->store->get('material_name_scheme');
        $fileMatTitle = $this->store->get('diss_material_title');
        $fileMatSub = $this->store->get('diss_material_sub_images');
        $fileMatLogo = $this->store->get('diss_material_logos');
        $fileMatBagit = $this->store->get('material_bagit_file');
        $fileMatArr = $this->store->get('material_arrangement_file');
        
        if ($fileMetaData) {
            $fileMetaData = $fileMetaData[0];
            
            if ($fileMetaData) {
                $fmdObj = file_load($fileMetaData[0]);
                $form2['material_metadata_file'] = $_SERVER['HTTP_HOST'].'/sites/default/files/'.$form2['material_acdh_repo_id'].'/'.$fmdObj->getFilename();
            }
        }
        
        if ($fileNameScheme) {
            $fileNameScheme = $fileNameScheme[0];
            if ($fileNameScheme) {
                $fnsObj = file_load($fileNameScheme[0]);
                $form2['material_name_scheme'] = $_SERVER['HTTP_HOST'].'/sites/default/files/'.$form2['material_acdh_repo_id'].'/'.$fnsObj->getFilename();
            }
        }
        
        if (count($fileMatTitle) > 0) {
            $fID = $fileMatTitle[0];
            if (!empty($fID)) {
                $mtdObj = file_load($fID);
                if ($mtdObj->getFilename()) {
                    $form2['diss_material_title'] = $_SERVER['HTTP_HOST'].'/sites/default/files/'.$form2['material_acdh_repo_id'].'/'.$mtdObj->getFilename();
                }
            }
        }
        if (count($fileMatSub) > 0) {
            $fmID = $fileMatSub[0];
            if (!empty($fmID)) {
                $msdObj = file_load($fmID);
                if ($msdObj->getFilename()) {
                    $form2['diss_material_sub_images'] = $_SERVER['HTTP_HOST'].'/sites/default/files/'.$form2['material_acdh_repo_id'].'/'.$msdObj->getFilename();
                }
            }
        }
        if ($fileMatLogo) {
            $fileMatLogo = $fileMatLogo[0];
            if ($fileMatLogo[0]) {
                $mlObj = file_load($fileMatLogo);
                $form2['diss_material_logos'] = $_SERVER['HTTP_HOST'].'/sites/default/files/'.$form2['material_acdh_repo_id'].'/'.$mlObj->getFilename();
            }
        }
        if ($fileMatBagit) {
            $fileMatBagit = $fileMatBagit[0];
            if ($fileMatBagit[0]) {
                $mbObj = file_load($fileMatBagit);
                $form2['material_bagit_file'] = $_SERVER['HTTP_HOST'].'/sites/default/files/'.$form2['material_acdh_repo_id'].'/'.$mbObj->getFilename();
            }
        }
        if ($fileMatArr) {
            $fileMatArr = $fileMatArr[0];
            if ($fileMatArr[0]) {
                $maObj = file_load($fileMatArr);
                $form2['material_arrangement_file'] = $_SERVER['HTTP_HOST'].'/sites/default/files/'.$form2['material_acdh_repo_id'].'/'.$maObj->getFilename();
            }
        }
        
        /*$dv = \Drupal\oeaw\DepAgreeConstants::getDataValidation();
        $form3['data_validation'] = $dv[$form3['data_validation']];
       */
        $num_updated = db_update('oeaw_forms')
            ->fields(array(
                    'status'=>  "closed"
            ))
            ->condition('userid', \Drupal::currentUser()->id(), '=')
            ->condition('repoid', $this->repoid, '=')
            ->condition('status', "open", '=')
            ->execute();
        
        $tcpdf = new \Drupal\oeaw\deppPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // set default monospaced font
        $tcpdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        $tcpdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $tcpdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $tcpdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $tcpdf->SetCreator('ACDH');
        $tcpdf->SetAuthor('ACDH');
        $tcpdf->SetTitle('Deposition Agreement');
        $tcpdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $tcpdf->setPrintHeader(false);
        $tcpdf->setPrintFooter(true);
        
        //$fontname = \TCPDF_FONTS::addTTFfont('modules/oeaw/fonts/Brandon_reg.ttf');
        //$fontnameBold = \TCPDF_FONTS::addTTFfont('modules/oeaw/fonts/Brandon_bld.ttf');
        
        $fontname = 'times';
        $fontnameBold = 'times';
        
        $tcpdf->SetFont($fontname, 'BI', 14);
        $tcpdf->AddPage();

        $tcpdf->SetLineWidth(0.7);
        $tcpdf->setCellHeightRatio(3);
        $style2 = array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 71, 187));
        // Line
        $tcpdf->Line(10, 10, 200, 10, $style2);
        $tcpdf->Line(10, 285, 200, 285, $style2);

        $tcpdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 71, 187)));
        $tcpdf->Rect(10, 10, 8, 90, 'DF', $style2, array(0, 71, 187));
        $tcpdf->Rect(192, 100, 8, 185, 'DF', $style2, array(0, 71, 187));
        $tcpdf->SetFont($fontname, '', 7);
        $tcpdf->Image('modules/oeaw/images/oeaw.png', 35, 30, 60, 26, 'PNG', '', '', true, 150, '', false, false, 0, false, false, false);

        $tcpdf->SetFont($fontnameBold, 'b', 60);
        $tcpdf->SetXY(30, 50);
        $tcpdf->SetTextColor(0, 0, 0);
        $tcpdf->Cell(0, 0, 'DEPOSITION', 0, 0, 'L', 0, '');
        $tcpdf->SetXY(30, 70);
        $tcpdf->Cell(0, 0, 'AGREEMENT', 0, 0, 'L', 0, '');


        $tcpdf->SetFont($fontnameBold, '', 7, '', false);
        $tcpdf->SetXY(10, 45);
        $tcpdf->Rotate(90);
        $tcpdf->SetTextColor(255, 255, 255);
        $tcpdf->Cell(0, 0, 'WWW.OEAW.AC.AT', 0, 0, 'L', 0, '');
        $tcpdf->StopTransform();
        $tcpdf->Rotate(0);
        $tcpdf->SetLineWidth(0.5);
        $tcpdf->setCellHeightRatio(1.5);
      
        $tcpdf->SetFont($fontname, '', 14);
        $tcpdf->SetTextColor(0, 0, 0);
        
        $fontnames = array('normal' => $fontname, 'bold' => $fontnameBold);
        
        //generate the pages
        if (empty($form1) || empty($form2) || empty($form3)) {
            echo "<pre>";
            echo "form1";
            var_dump($form1);
            echo "form2";
            var_dump($form2);
            echo "form3";
            var_dump($form3);
            echo "</pre>";

            die();



            $msg = base64_encode("This FORM is OUTDATED!");
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }
        
        $this->generatePdfPage($tcpdf, $form1, "DEPOSITOR", \Drupal\oeaw\DepAgreeConstants::$depTXT, $fontnames);
        $this->generatePdfPage($tcpdf, $form2, "DESCRIPTION OF MATERIAL, EXTENT, FILES", \Drupal\oeaw\DepAgreeConstants::$descTXT, $fontnames);
        $this->generatePdfPage($tcpdf, $form3, "TRANSFER PROCEDURES", \Drupal\oeaw\DepAgreeConstants::$transferTXT, $fontnames);
        //$this->generatePdfPage($tcpdf, $form4, "CREATORS", \Drupal\oeaw\DepAgreeConstants::$lastTXT, $fontnames);
 
        $tcpdf->AddPage();
        $signTXT = '
            <table width="100%">
                <tr>
                    <td colspan="2"><h1>Signatures<br /></h1></td>
                </tr>
                <tr width="50%">
                        <td align="center"><b>For the Repository<br/></b></td>
                        <td align="center"><b>For the Depositor<br/></b></td>
                </tr>
                <tr width="50%">
                        <td align="center" style="padding-top:20px;"><br />---------------------------------</td>
                        <td align="center" style="padding-top:20px;"><br />---------------------------------</td>
                </tr>
                <tr width="50%">
                        <td align="center">Date, Signature</td>
                        <td align="center">Date, Signature</td>
                </tr>
            </table>';
        
        $tcpdf->writeHTML($signTXT, true, false, false, false, '');
        //Close and output PDF document
        $tcpdf->Output($_SERVER['DOCUMENT_ROOT'].'/sites/default/files/'.$form2['material_acdh_repo_id'].'/'.$form2['material_acdh_repo_id'].'.pdf', 'F');
        
        $this->deleteStore($form1);
        $this->deleteStore($form2);
        $this->deleteStore($form3);
        //$this->deleteStore($form4);

        $this->store->delete('form1Val');
        $this->store->delete('form2Val');
        $this->store->delete('form3Val');
        //$this->store->delete('form4Val');
        $response = new RedirectResponse(\Drupal::url('oeaw_form_success', ['url' => $form2['material_acdh_repo_id']]));
        $response->send();
        return;
    }
    
    public function generatePdfPage(TCPDF $tcpdf, array $formData, string $title, string $ftrTXT = "", array $fontnames): TCPDF
    {
        
        
        // add a page
        $tcpdf->AddPage();
        $tcpdf->SetLineWidth(0.5);
        $tcpdf->setCellHeightRatio(1.5);
        $style = array('width' => 3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 71, 187));
        $tcpdf->Line(150, 11, 200, 11, $style);

        $tcpdf->SetXY(110, 9);
        $tcpdf->SetFont($fontnames['normal'], '', 8, '', false);
        $tcpdf->SetTextColor(0, 71, 187);
        $tcpdf->Cell(0, 0, 'DEPOSITION AGREEMENT', 0, 0, 'L', 0, '');
        $tcpdf->StopTransform();
        
        $tcpdf->SetTextColor(0, 0, 0);
        $tcpdf->SetFont($fontnames['normal'], '', 12, '', false);
        $tcpdf->SetXY(10, 20);
        
       
        $txt = "<style>
   
    td.title {        
        border-right: 1px solid #0047BB;
        border-top: 1px solid #0047BB;
        border-bottom: 1px solid #0047BB;
        color:#0047BB;
        align:left;
        padding-left: 5px;
    }
	
    td.value {
	border-left: 1px solid black;
        border-top: 1px solid black;
        border-bottom: 1px solid black;
        color: black;
        align: right:
        padding-right: 5px;
    }
</style>";
       
        // set some text to print
        $tcpdf->SetFont($fontnames['bold'], '', 20, '', false);
        $tcpdf->SetTextColor(0, 0, 0);
        $tcpdf->Cell(0, 0, $title, 0, 0, 'L', 0, '');
        //$txt .= "<h1>".$title."</h1><br/>";
        $tcpdf->SetXY(10, 40);
        $tcpdf->SetTextColor(0, 0, 0);
        $tcpdf->SetFont($fontnames['normal'], '', 12, '', false);
        foreach ($formData as $k => $v) {
            if (\Drupal\oeaw\DepAgreeConstants::getPDFLng($k)) {
                $text = \Drupal\oeaw\DepAgreeConstants::getPDFLng($k);
            } else {
                $text = $k;
            }
            
            if ($k === "candidate_confirmation" || $k === "fields_count") {
                continue;
            }
            
            if (is_array($v)) {
                $txt .= '<table cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td class="title" align="left">&nbsp;&nbsp;'.$text.'</td><td class="value" align="right">&nbsp;&nbsp;';
                foreach ($v as $key => $val) {
                    if ($val) {
                        $txt .= $key.'&nbsp;&nbsp;<br />';
                    }
                }
                $txt .= '</td></tr>
                    </table>';
            } else {
                $txt .= '
                <table cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td class="title" align="left">&nbsp;&nbsp;'.$text.'&nbsp;&nbsp;</td>
                        <td class="value" align="right">&nbsp;&nbsp;'.$v.'&nbsp;&nbsp;</td>        
                    </tr>
                </table>';
            }
        }
        $tcpdf->writeHTML($txt, true, false, false, false, '');
        
        $tcpdf->Line(8, 280, 60, 280, $style);
        if ($ftrTXT) {
            $tcpdf->writeHTML($ftrTXT, true, false, false, false, '');
        }
        // print a block of text using Write()
        
        return $tcpdf;
    }
    
    protected function deleteStore(array $array)
    {
        foreach ($array as $key => $value) {
            $this->store->delete($key);
        }
    }
}
