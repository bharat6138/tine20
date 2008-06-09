<?php
/**
 * Tine 2.0
 *
 * @package     Crm
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Thomas Wadewitz <t.wadewitz@metaways.de>
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id$
 */

/**
 * backend class for Tinebase_Http_Server
 *
 * This class handles all Http requests for the Crm application
 *
 * @package     Crm
 */
class Crm_Http extends Tinebase_Application_Http_Abstract
{
    protected $_appname = 'Crm';
    
    /**
     * Returns all JS files which must be included for this app
     *
     * @return array Array of filenames
     */
    public function getJsFilesToInclude()
    {
        return array(
            'Crm/js/Crm.js',
            'Crm/js/LeadEditDialog.js',
            'Crm/js/LeadState.js',
        );
    }
    
    /**
     * create edit lead dialog
     *
     * @param int $_leadId
     * @todo catch permission denied exceptions only
     * 
     */
    public function editLead($_leadId)
    {
         if(empty($_leadId)) {
            $_leadId = NULL;
        }
        
        $locale = Zend_Registry::get('locale');
        $currentAccount = Zend_Registry::get('currentAccount');
        
        $view = new Zend_View();
         
        $view->setScriptPath('Tinebase/views');
        $view->formData = array();
        
        $crmJson = new Crm_Json;        
        
        $controller = Crm_Controller::getInstance();
        
        if($_leadId !== NULL && $lead = $controller->getLead($_leadId)) {
            $leadData = $lead->toArray();

            // add contact links
            $leadData['contacts'] = array();
            $contact_links = $controller->getLinksForApplication($_leadId, 'Addressbook');
            foreach($contact_links as $contact_link) {
                try {
                    $contact = Addressbook_Controller::getInstance()->getContact($contact_link['recordId']);
                    $contactArray = $contact->toArray();
                    $contactArray['link_remark'] = $contact_link['remark'];
                    $leadData['contacts'][] = $contactArray;                    
                } catch (Exception $e) {
                    // do nothing
                    // catch only permission denied exception
                }
            }

            //Zend_Registry::get('logger')->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($leadData['contacts'], true));
            
            // add task links
            $leadData['tasks'] = array();
            $taskLinks = $controller->getLinksForApplication($_leadId, 'Tasks');
            // @todo    move that to controller?
            foreach ( $taskLinks as $taskLink ) {
                try {
                    $task = Tasks_Controller::getInstance()->getTask($taskLink['recordId']);            
                    $taskArray = $task->toArray();

                    $creator = Tinebase_User::getInstance()->getUserById($task->created_by);
                    $taskArray['creator'] = $creator->accountFullName;
                    
                    if ($task->last_modified_by != NULL) {
                        $modifier = Tinebase_User::getInstance()->getUserById($task->last_modified_by);
                        $taskArray['modifier'] = $modifier->accountFullName;         
                    }

                    // @todo write function for that: getStatusById()
                    $stati = Tasks_Controller::getInstance()->getStati()->toArray();
                    foreach ($stati as $status) {
                        if ($status['id'] == $taskArray['status_id']) {
                            $taskArray['status_realname'] = $status['status_name'];
                            $taskArray['status_icon'] = $status['status_icon'];
                        }
                    }

                    $leadData['tasks'][] = $taskArray;  
                    
                } catch (Exception $e) {
                    // do nothing
                    //Zend_Registry::get('logger')->debug(__METHOD__ . '::' . __LINE__ . ' ' . $e->__toString());
                }
            }
            
            // @todo is that needed?
            //$folder = Tinebase_Container::getInstance()->getContainerById($lead->container);
            //$leadData['container'] = $folder->toArray();
            
            $products = $controller->getProductsByLeadId($_leadId);
            $leadData['products'] = $products->toArray();
            
        } else {
            $leadData = $controller->getEmptyLead()->toArray();
            $leadData['products'] = array();                
            $leadData['contacts'] = array();   
            $leadData['tasks'] = array();                                   
            
            $personalFolders = Zend_Registry::get('currentAccount')->getPersonalContainer('Crm', $currentAccount, Tinebase_Container::GRANT_READ);
            foreach($personalFolders as $folder) {
                $leadData['container']     = $folder->toArray();
                break;
            }
            
        }

        $_leadTypes = $controller->getLeadtypes('leadtype','ASC');
        $view->formData['comboData']['leadtypes'] = $_leadTypes->toArray();
        
        $_leadStates =  $controller->getLeadStates('leadstate','ASC');
        $view->formData['comboData']['leadstates'] = $_leadStates->toArray();
        
        $_leadSources =  $controller->getLeadSources('leadsource','ASC');
        $view->formData['comboData']['leadsources'] = $_leadSources->toArray();

        $_productSource =  $controller->getProducts('productsource','ASC');
        $view->formData['comboData']['productsource'] = $_productSource->toArray();

        $view->jsExecute = 'Tine.Crm.LeadEditDialog.display(' . Zend_Json::encode($leadData) . ' );';

        $view->configData = array(
            'timeZone' => Zend_Registry::get('userTimeZone'),
            'currentAccount' => Zend_Registry::get('currentAccount')->toArray()
        );
        
        $view->title="edit lead";

        $view->isPopup = true;
        
        $includeFiles = Tinebase_Http::getAllIncludeFiles();
        $view->jsIncludeFiles  = $includeFiles['js'];
        $view->cssIncludeFiles = $includeFiles['css'];
        
        header('Content-Type: text/html; charset=utf-8');
        echo $view->render('mainscreen.php');
    }

   	/**
     * export lead
     * 
     * @param	integer lead id
     * @param	format	pdf or csv or ...
     * 
     * @todo	implement csv export
     */
	public function exportLead($_leadId, $_format = 'pdf')
	{
		// get lead
		$lead = Crm_Controller::getInstance()->getLead($_leadId);
		
		// export
		if ( $_format === "pdf" ) {
			$pdf = new Crm_Pdf();
			$pdfOutput = $pdf->getLeadPdf($lead);

			header("Content-Disposition: inline; filename=lead.pdf"); 
			header("Content-type: application/x-pdf"); 
			echo $pdfOutput; 
			
		}
	}    
}