<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Drupal\oeaw;

/**
 * Description of DepAgreeConstants
 *
 * @author nczirjak
 */
class DepAgreeConstants
{
    public static $depTXT = "This agreement is between the organisation or person(s) authorised to transfer and deposit digital resources 
        (hereinafter ‘the Depositor’) and ACDH-repo (hereinafter ‘the Repository’), which is run and represented by the Austrian Centre for 
        Digital Humanities of the Austrian Academy of Sciences. The agreement concerns transfer, curation, archival, and dissemination of 
        electronic resources described in the section 'Description of Material' <br /><br />"
            . "<b>Repository</b><br />
            ACDH-repo<br />
            Austrian Centre for Digital Humanities<br />
            Austrian Academy of Sciences<br />
            Sonnenfelsgasse 19<br />
            1010 Wien<br /><br /><br />
            
        ";
        
    
    public static $descTXT = "The resources being agreed upon are described below and comprise the Submission Information Package (SIP). "
            . "A change in the extent of the resources after signing this agreement is only possible by mutual agreement between the Depositor "
            . "and the Repository. File formats included should adhere to the preferred and accepted formats specified in XXX."
            . "<br /><br />"
            . "For presentation and dissemination the information provided in ‘Description of Material’ might be used.<br /><br />
        The Depositor and the Repository agree on following procedures to check for data integrityvalidation (please tick as appropriate):        
        
            <input type='checkbox' name='1' val='1'> The donor/depository has provided a tab-delimited text file providing full object paths and filenames for the all objects being submitted, with an MD5 checksum for each object.  The repository will perform automated validation.<br />
            <input type='checkbox' name='2' val='2'> Based on incomplete information supplied by the depositor/donor prior to transfer, the repository will carry out selected content and completeness checks to verify that the transmitted data is what is expected, and that it is complete. .<br />
            <input type='checkbox' name='3' val='3'>No data validation will be performed on objects submitted..<br />
        <br />
        The Repository reserves the right to reject data transfers at any stage of processing. The Repository will notify the Depositor of the reason of rejection, which can include:
        <ul>
            <li>The deposit does not conform to the agreed SIP definition</li>
            <li>The deposit does not contain the expected content</li>
            <li>The deposit is incomplete</li>
            <li>The deposit contains an unacceptable level of duplication (within itself and/or with existing content already held by the repository)</li>
            <li>The deposit includes insufficient metadata.  </li>
        </ul>        
        <br />
        Rejected data transfers will be returned to the Depositor using the original transfer method, and the Depositor will be given due notice. If relevant a replacement data transfer will be agreed between the Depositor and the Repository.<br /><br />
        The Repository will provide the Depositor with receipts at the following points:
        <ul>
            <li>a.)When data is first received</li>
            <li>b.)Once data has been successfully ingested</li>
            <li>c,)Once data is ready for publication</li>
        </ul>
        <br />
        Furthermore the Repository will provide the Depositor with a processing plan in due time.";
        
    public static $transferTXT = 'The Depositor will present data for transfer to the Repository in the formats described above, 
            within a single folder named as below. If possible the Depositor will present data in BagIt format with the filename stated below. 
            Furthermore the Depositor will supply metadata as described in XXX, in the format and to the specifications described there. 
            In addition the Depositor will choose an access mode and a licence, and provide information about sensible information.
            <br /><br />
            Data shall be transmitted to the repository by the Depositor on the following date or schedule, using the transfer medium described:            
            <ul>
                <li>Folder name or BagIt name</li>
                <li>Required metadata</li>
                <li>Transfer date</li>
            </ul>
            <br />            
            Transfer medium and method (please tick as appropriate):<br />
            <ul  style="list-style-type:square;">
                <li>Upload (max. 100MB)</li>
                <li>oeawCloud please provide the URL</li>
                <li>Link to file storage: please provide the URL</li>
                <li>With physical storage medium: we will contact you to clarify details</li>
            </ul>
                <br /><br />
            Where the transfer medium is supplied by the Repository, the Repository accepts no responsibility for any loss or damage to the Depositor’s systems which may result from its use.
            <br />
            Where the transfer medium is supplied by the Depositor, the Repository will ensure to return it to the Depositor when it isn’t needed anymore.
            ';
    
    public static $lastTXT = ""
            . "For presentation and dissemination the information provided in ‘Description of Material’ might be used.<br /><br /> 
            The Depositor and the Repository agree on following procedures to check for data completeness and integrity (please tick as appropriate):
            <ul>            
                <li>The Depositor has provided a file listing full object paths and filenames for the all objects being submitted, with an MD5 checksum for each object.  The Repository will perform automated validation.</li>
                <li>Based on incomplete information supplied by the Depositor prior to transfer, the Repository will carry out content and completeness checks to verify that the transmitted data is what is expected, and that it is complete.</li>
                <li>If no checksums or other information about the data is provided by the Depositor, the Repository cannot perform any integrity checks on submitted data.</li>
            </ul>
            <br />
            <br />
            The Repository reserves the right to reject data transfers at any stage of processing. The Repository will notify the Depositor of the reason of rejection, which can include:
            <ul>    
                <li>The deposit does not conform to the agreed SIP definition</li>
                <li>The deposit does not contain the expected content</li>
                <li>The deposit is incomplete</li>
                <li>The deposit contains an unacceptable level of duplication (within itself and/or with existing content already held by the repository)</li>
                <li>The deposit includes insufficient metadata.</li>
            </ul>
            
             <br />
            Rejected data transfers will be returned to the Depositor using the original transfer method, and the Depositor will be given due notice. If relevant a replacement data transfer will be agreed between the Depositor and the Repository.<br />
             <br />
            The Repository will provide the Depositor with receipts at the following points:
            <ul  style='list-style-type:a;'>
                <li>When data is first received</li>
                <li>Once data has been successfully ingested</li>
                <li>Once data is ready for publication</li>
            </ul>
             <br />
            Furthermore the Repository will provide the Depositor with a processing plan in due time.<br /><br />
            
            <h2>Rights and obligations of the Depositor</h2>
            <br />
            <ul>
                <li>By signing and submitting this licence, the depositor agrees to make available digital resources through the Repository.</li>
                <li>The Depositor grants the Repository a non-exclusive license for the submitted resources specified in ‘Description of Material’.</li>
                <li>The depositor agrees that the Repository may for purposes of preservation and dissemination convert the submitted resources to any medium or format.</li>
                <li>The depositor agrees that the Repository may produce copies of the submitted resources for purposes of security, back-up, preservation, and dissemination.</li>
                <li>The Depositor declares that the resources correspond to the specification provided in ‘Description of Material’.</li>
                <li>The Depositor declares that he is a holder of rights to the resources, or the only holder of rights to the resources, under the relevant legislation or otherwise, and/or is entitled to act in the present matter with the permission of other parties that hold rights.</li>
                <li>The Depositor warrants to the best of his/her knowledge that the submission does in no way infringe on anyone's copyright and/or breaches any existing agreements.</li>
                <li>The Depositor declares that the resources contain no data or other elements that are contrary to the law or public regulations.</li>
                <li>The Depositor will inform the Repository about any sensitive information contained in the resources.</li>
                <li>The Depositor will inform the Repository about changes in rights to the resources.</li>
                <li>The Depositor obliges to provide all necessary information and metadata for the resources corresponding to the specifications agreed upon with the repository.</li>
                <li>The Depositor will supply resources by means of a method, format and medium deemed acceptable by the Repository.</li>
                <li>The Depositor will provide missing information and execute tasks for the preparation of the resources for archiving in a reasonable time. In this regard reasonable time means a period of three months.</li>
                <li>The Depositor retains the right to archive or publish the resources or parts of it in other institutions or services.</li>
                <li>The Depositor indemnifies the Repository against all claims made by other parties against the Repository with regard to the resources.</li>
            </ul>
            <br />
            <h2>Rights and obligations of the Repository</h2>
            <br />
            <ul>
                <li>The Repository is authorised to include the resources described in ‘Description of Material’ in its archive. </li>
                <li>The Repository is allowed to transfer resources to an available carrier, through any method and in any form.</li>
                <li>The Repository ensures, to the best of its ability and resources, that the resources remain legible and accessible by providing a set of dissemination methods in agreement with the Depositor for the duration of this agreement.</li>
                <li>The Repository ensures, to the best of its ability and resources, that the deposited resources are archived in a sustainable manner for the duration of this agreement.</li>
                <li>The Repository will, as far as possible, preserve resources unchanged in their original digital format, taking account of current technology and the costs of implementation. The Repository has the right to modify the format and/or functionality of the resources if this is necessary to facilitate the digital sustainability, distribution or reuse of resources.</li>
                <li>The Repository will explicitly name the depositor of the submitted resources.</li>
                <li>ACDH-repo has the right to reproduce and/or distribute the submitted data including the metadata. ACDH-repo additionally has the right to translate metadata.</li>
                <li>The Repository is authorised to make the resources (or substantial parts thereof) available to Third Parties by means of on-line transmission. In addition, the Repository has the right, on the instruction of third parties or otherwise, to make a copy of the dataset or to grant Third Parties permission to download a copy.</li>
                <li>The Repository shall, to the best of its ability and resources, ensure that effective technical and other measures are in place to prevent unauthorized third parties from gaining access to restricted resources.</li>
                <li>The Repository will not make any alteration to the data other than allowed by this agreement.</li>
            </ul>
            <br />
            <h2>Availability of resources</h2>
            <br />
            <ul>
                <li>The Repository will make the resources available to third parties in accordance with the access conditions agreed with the Depositor.</li>
                <li>The Repository will make the resources available only to third parties who have agreed to comply with the Terms of Use. Unless agreed otherwise with the Depositor, the use of Content is subject to the General Terms of Use laid down by the Repository. </li>
                <li>The Repository can make Content (or substantial parts thereof) available to third parties:</li>
                <ul>
                    <li>if the Repository is required to do so by legislation or regulations, a court decision, or by a regulatory or other institution</li>
                    <li>if this is necessary for the preservation of the resources</li>
                    <li>(to a similar institution) if the Repository ceases to exist and/or its activities in the field of data-archiving are terminated</li>
                </ul>    
                <li>The Repository shall publish the metadata provided by the Depositor and make them freely available under CC0. Other documentation that relates to the dataset and is provided by the Depositor shall be published and made freely available, unless the Depositor has specified that certain documents must not be made freely available.</li>
                <li>The general information about the research and the metadata relating to the resources shall be included in the Repository's databases and publications.</li>
            </ul>
            <br />
            <h2>Withdrawal of resources</h2>
            <br />
            <ol type='A'>
                <li>If sufficient indispensable grounds exist, the Depositor has the right to request the Repository not to make the resources available for a temporary period or permanently. In such cases, the Repository may retain the resources in the archive, but will no longer allow Third Parties to access them.</li>
                <li>If sufficient indispensable grounds exist, the Repository has the right to remove the resources from the archive wholly or in part, or to restrict or prevent access on a temporary or permanent basis. The Repository will inform the Depositor in such cases.</li>
            </ol>
            <br />
            <h2>Warranty and Liability</h2>
            <br />
            <ol type='A'>
                <li>The Repository accepts responsibility for providing the technical infrastructure for data archiving and discovery.</li>
                <li>The Repository reserves the right to temporarily suspend server operations for urgent internal reasons and will endeavour to keep downtimes to a minimum.</li>
                <li>The Repository ensures the functionality of the persistent identifiers assigned to the resources, subject to availability of the service by external partners.</li>
                <li>The Repository is not obliged to check whether the rights of Third Parties are violated by the archiving of resources. Specifically, the Repository is not responsible for data contents, the lawfulness of data provision or access to data.</li>
                <li>The Repository accepts no liability in the event that all or part of resources is lost.</li>
                <li>The Repository accepts no liability for any damage or losses resulting from acts or omissions by Third Parties to whom the Repository has made resources available.</li>
            </ol>
            <br />
            <h2>Costs</h2>
            <br />
            The deposition of resources into the repository is free of charge.
            <br />
            <h2>Death of the Depositor</h2>
            <br />
            Following the death of the Depositor, or in the event that the Depositor's organisation ceases to exist, Content in the ‘Restricted Access’ category shall automatically be transferred to the ‘Public’ category. This is not applicable if resources contain personal data or such material, which copyright moves to the heirs of the original copyright owner.
            <br />            
            <h2>Period of Validity and Termination</h2>
            <ol start='1'>
                <li>This agreement comes into effect as of the date of signature of the parties. The validity term of this agreement is sine die.</li>
                <li>Either party may terminate this agreement at any time on the material breach or repeated other breaches by the other party of any obligation on its part under this agreement, by serving a written notice on the other party identifying the nature of the breach. The termination will become effective thirty (30) days after receipt of the written notice, unless during the relevant period of thirty (30) days the defaulting party remedies the breach.</li>
                <li>This agreement may be terminated by either party on written notice if the other party becomes insolvent or bankrupt, if the Repository's project ends or if the Repository withdraws or ceases operations. The termination will become effective thirty (30) days after receipt of the written notice.</li>
                <li>Upon termination of this agreement, the repository shall only be obliged to remove Metadata dissemination material provided by the depositor if the depositor request their removal. Removal shall happen no later than 30 days after such a request has been received by the repository.</li>
                <li>If the Repository ceases to exist or terminates its archiving activities, it will attempt to transfer the data streams to a similar organisation that will continue the agreement with the Depositor under similar conditions and inform the Depositor. The Repository will also provide the opportunity for the Depositor to get a copy of the resources.</li>
                <li>Termination of this agreement does not affect any prior valid agreement made by either party with Third Parties.</li>
            </ol>
            <br />            
            <h2>Miscellaneous</h2>
            <ol start='1'>
                <li>If any term of this agreement is held by a court of competent jurisdiction to be invalid or unenforceable, then this agreement, including all of the remaining terms, will remain in full force and effect as if such invalid or unenforceable term had never been included.</li>
                <li>This agreement may be supplemented, amended or modified only by the mutual agreement of the parties.  Any modification proposed by the Repository must be notified to the Depositor in writing. The Depositor shall be allowed at least two months from the date of reception of the notice to accept the new agreement. If the modifications are not accepted by the Depositor in writing within the allowed period, the modifications are presumed to have been rejected. If the proposed modifications are rejected by the Depositor, the Repository has the right to terminate this agreement against 31 December of any year, with a one month notice.</li>
            </ol>            
            <br />
            <h2>Jurisdiction</h2>
            The provisions of Austrian law apply to this agreement. Place of jurisdiction is Vienna.
            ";
    
    
    public static function getPDFLng(string $lng): string
    {
        $lngData = array(
            'title' => 'Title',
            'l_name' => 'Last Name',
            'f_name' => 'First Name',
            'institution' => 'Institution',
            'city' => 'City',
            'address' => 'Address',
            'zipcode' => 'Zipcode',
            'email' => 'Email',
            'phone' => 'Phone',
            'material_acdh_repo_id' => 'Material ACDH RepoID',
            'material_title' => 'Material Title',
            'material_ipr' => 'Intellectual Property Rights (IPR):',
            'material_metadata' => 'Metadata',
            'material_metadata_file' => 'Metadata Resource',
            'material_preview' => 'Preview',
            'material_mat_licence' => 'Material Licence',
            'material_scope_content_statement' => 'Scope And Content Statement',
            'material_file_size_byte' => 'File size byte',
            'material_file_number' => 'File Number',
            'material_folder_number' => 'Folder Number',
            'material_soft_req' => 'Software Req.',
            'material_arrangement' => 'Arrangement',
            'material_name_scheme' => 'Name Scheme',
            'material_other_file_type' => 'Other File Type',
            'material_other_file_formats' => 'Other File Formats',
            'material_file_formats' => 'File Formats',
            'material_file_types' => 'File types',
            'folder_name' => 'Folder Name',
            'transfer_date' => 'Transfer Date',
            'transfer_method' => 'Transfer Method',
            'data_validation' => 'Data Validation',
            'creator_title_' => 'Creator Title',
            'creator_l_name_' => 'Creator Last Name',
            'creator_f_name_' => 'Creator First Name',
            'creator_institution_' => 'Creator Institution',
            'creator_city_' => 'Creator City',
            'creator_address_' => 'Creator Address',
            'creator_zipcode_' => 'Creator Zipcode',
            'creator_phone_' => 'Creator Phone',
            'creator_email_' => 'Creator Email',
            'fields_count_' => 'The number of the Creators',
            'embargo_question' => 'List of file formats included?',
            'embargo_date' => 'Embargo Date',
            'diss_material_title' => 'Diss. Mat. Title image',
            'diss_material_sub_images' => 'Diss. Mat. Subordinate images',
            'diss_material_logos' => 'Diss. Mat. Logos',
            'bagit_question' => 'Do you have a bagit file?',
            'material_bagit_file' => 'Bagit File',
            'material_arrangement_file' => 'Arrangement File'
            
            
        );
        
        if (array_key_exists($lng, $lngData)) {
            return $lngData[$lng];
        } else {
            foreach ($lngData as $key => $val) {
                $lngE = explode("_", $lng);
                $lngE = end($lngE);
                $lngN = str_replace('_'.$lngE, '', $lng);
                if (strpos($key, $lngN) !== false) {
                    return $val;
                }
            }
        }
        return false;
    }
    
    
    public static function getMaterialLicences()
    {
        $licenes = array(
            'Public Domain Mark' => t('Public Domain Mark'),
            'No Copyright - non commercial re-use only' => t('No Copyright - non commercial re-use only'),
            'No Copyright - other known legal restrictions ' => t('No Copyright - other known legal restrictions '),
            'CC0' => t('CC0'),
            'CC-BY' => t('CC-BY'),
            'CC-BY-SA' => t('CC-BY-SA'),
            'CC-BY-ND' => t('CC-BY-ND'),
            'CC-BY-NC' => t('CC-BY-NC'),
            'CC-BY-NC-SA' => t('CC-BY-NC-SA'),
            'CC-BY-NC-ND' => t('CC-BY-NC-ND'),
            'In Copyright' => t('In Copyright'),
            'In Copyright - Educational Use Permitted' => t('In Copyright - Educational Use Permitted'),
            'In Copyright - EU Orphan Work' => t('In Copyright - EU Orphan Work'),
            'Copyright Not Evaluated' => t('Copyright Not Evaluated')
        );
        return $licenes;
    }
    
    public static function getFileTypes()
    {
        $fileTypes = array();
        $fileTypes["3DVirtual"] = "3D Data and Virtual Reality";
        $fileTypes["AudioFiles"] = "Audio Files";
        $fileTypes["Database"] = "DataBase";
        $fileTypes["Images"] = "Images (raster)";
        $fileTypes["PDFDocuments"] = "PDF Documents";
        $fileTypes["Spreadsheets"] = "Spreadsheets";
        $fileTypes["StructFiles"] = "Structured text files (e. g. XML files)";
        $fileTypes["TextDocuments"] = "Text Documents";
        $fileTypes["VectorImages"] = "Vector Images";
        $fileTypes["VideoFiles"] = "Video Files";
        $fileTypes["Websites"] = "Websites";
        return $fileTypes;
    }
    
    public static function getFileFormats()
    {
        $fileFormats = array();
        $fileFormats["AAC_MP4"]="AAC/MP4";
        $fileFormats["AI"]="AI";
        $fileFormats["AIFF"]="AIFF";
        $fileFormats["ASF_WMV"]="ASF/WMV";
        $fileFormats["AVI"]="AVI";
        $fileFormats["BAK"]="BAK";
        $fileFormats["BMP"]="BMP";
        $fileFormats["BWF"]="BWF";
        $fileFormats["CGM"]="CGM";
        $fileFormats["COLLADA"]="COLLADA";
        $fileFormats["CPT"]="CPT";
        $fileFormats["CSV"]="CSV";
        $fileFormats["DBF"]="DBF";
        $fileFormats["DNG"]="DNG";
        $fileFormats["DOC"]="DOC";
        $fileFormats["DOCX"]="DOCX";
        $fileFormats["DTD"]="DTD";
        $fileFormats["DWF"]="DWF";
        $fileFormats["DWG"]="DWG";
        $fileFormats["DXF"]="DXF";
        $fileFormats["FLAC"]="FLAC";
        $fileFormats["FLV"]="FLV";
        $fileFormats["FMP"]="FMP";
        $fileFormats["GIF"]="GIF";
        $fileFormats["HTML"]="HTML";
        $fileFormats["JPEG"]="JPEG";
        $fileFormats["JPEG2000"]="JPEG2000";
        $fileFormats["JSON"]="JSON";
        $fileFormats["MAFF"]="MAFF";
        $fileFormats["MDB"]="MDB";
        $fileFormats["MHTML"]="MHTML";
        $fileFormats["MJ2"]="MJ2";
        $fileFormats["MKV"]="MKV";
        $fileFormats["MOV"]="MOV";
        $fileFormats["MP3"]="MP3";
        $fileFormats["MP4"]="MP4";
        $fileFormats["MPEG"]="MPEG";
        $fileFormats["MXF"]="MXF";
        $fileFormats["OBJ"]="OBJ";
        $fileFormats["ODB"]="ODB";
        $fileFormats["ODS"]="ODS";
        $fileFormats["ODT"]="ODT";
        $fileFormats["OGG"]="OGG";
        $fileFormats["PDF (other)"]="PDF (other)";
        $fileFormats["PDF_A-1"]="PDF/A-1";
        $fileFormats["PDF_A-2"]="PDF/A-2";
        $fileFormats["PDF_A-3"]="PDF/A-3";
        $fileFormats["PLY"]="PLY";
        $fileFormats["PNG"]="PNG";
        $fileFormats["PostScript"]="PostScript";
        $fileFormats["PSD"]="PSD";
        $fileFormats["RF64_MBWF"]="RF64/MBWF";
        $fileFormats["RTF"]="RTF";
        $fileFormats["SGML"]="SGML";
        $fileFormats["SIARD"]="SIARD";
        $fileFormats["SQL"]="SQL";
        $fileFormats["STL"]="STL";
        $fileFormats["SVG"]="SVG";
        $fileFormats["SXC"]="SXC";
        $fileFormats["SXW"]="SXW";
        $fileFormats["TIFF"]="TIFF";
        $fileFormats["TSV"]="TSV";
        $fileFormats["TXT"]="TXT";
        $fileFormats["U3D"]="U3D";
        $fileFormats["VRML"]="VRML";
        $fileFormats["WARC"]="WARC";
        $fileFormats["WAV"]="WAV";
        $fileFormats["WMA"]="WMA";
        $fileFormats["X3D"]="X3D";
        $fileFormats["XHTML"]="XHTML";
        $fileFormats["XLS"]="XLS";
        $fileFormats["XLSX"]="XLSX";
        $fileFormats["XML"]="XML";
        $fileFormats["XSD"]="XSD";
        
        return $fileFormats;
    }
    
    public static function getTransferMedium()
    {
        /*$transferMeth = array();
        $transferMeth["UPLOAD"] = "Upload (max. 100MB)";
        $transferMeth["OEAWCLOUD"] = "oeawCloud please provide the URL";
        $transferMeth["LINK"] = "Link to file storage: please provide the URL";
        $transferMeth["PHYSICAL"] = "With physical storage medium: we will contact you to clarify details";
        */
        
        $transferMeth = array(
            'UPLOAD' => t('Upload (max. 100MB)'),
            'OEAWCLOUD' => t('oeawCloud please provide the URL'),
            'LINK' => t('Link to file storage: please provide the URL'),
            'PHYSICAL' => t('With physical storage medium: we will contact you to clarify details')
        );
        
        return $transferMeth;
    }
    
    public static function getDataValidation()
    {
        $data = array();
        $data[0] = "The donor/depository has provided a tab-delimited text file providing full object paths and filenames for the all objects being submitted, with an MD5 checksum for each object.  The repository will perform automated validation.";
        $data[1] = "Based on incomplete information supplied by the depositor/donor prior to transfer, the repository will carry out selected content and completeness checks to verify that the transmitted data is what is expected, and that it is complete.";
        $data[2] = "No data validation will be performed on objects submitted.";
        return $data;
    }
    
    public static function getIntegrityChecks()
    {
        $data = array();
        $data[0] = "The Depositor has provided a file listing full object paths and filenames for the all objects being submitted, with an MD5 checksum for each object.  The Repository will perform automated validation.";
        $data[1] = "Based on incomplete information supplied by the Depositor prior to transfer, the Repository will carry out content and completeness checks to verify that the transmitted data is what is expected, and that it is complete.";
        $data[2] = "If no checksums or other information about the data is provided by the Depositor, the Repository cannot perform any integrity checks on submitted data.";
        return $data;
    }
    
    public static function getAccessMode()
    {
        $data = array();
        $data['PUB'] = "Public content (PUB): free access to the general public without any restriction. The classification of a resource as public content does not mean that the resources may be used for any purpose. The permissible types of use are further detailed by the licence accompanying every resource.";
        $data['ACA'] = "Academic content (ACA): to access the resource the user has to register as an academic user. This is accomplished by authentication with the home identity provider by means of the Identity Federation.";
        $data['RES'] = "Restricted content (RES): includes resources with a special access mode. Special authorisation rules apply that are detailed in the accompanying metadata record.";
        return $data;
    }
}
