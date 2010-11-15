<?php
class Invoice extends AppModel {

    var $name = 'Invoice';
   
   
    //The Associations below have been created with all possible keys, those that are not needed can be removed
    var $belongsTo = array(
            'ClientsContract' => array('className' => 'ClientsContract',
                                'foreignKey' => 'contract_id',
                                'conditions' => '',
                                'fields' => '',
                                'order' => ''
            )
    );

    var $hasMany = array(
            'InvoicesItem' => array('className' => 'InvoicesItem',
                                'foreignKey' => 'invoice_id',
                                'dependent' => true,
                                'conditions' => '',
                                'fields' => '',
                                'order' => '',
                                'limit' => '',
                                'offset' => '',
                                'exclusive' => '',
                                'finderQuery' => '',
                                'counterQuery' => ''
            ),
            'InvoicesPayment' => array('className' => 'InvoicesPayment',
                                'foreignKey' => 'invoice_id',
                                'dependent' => true,
                                'conditions' => '',
                                'fields' => '',
                                'order' => '',
                                'limit' => '',
                                'offset' => '',
                                'exclusive' => '',
                                'finderQuery' => '',
                                'counterQuery' => ''
            ),
            'EmployeesPayment' => array('className' => 'EmployeesPayment',
                                'foreignKey' => 'invoice_id',
                                'dependent' => true,
                                'conditions' => '',
                                'fields' => '',
                                'order' => '',
                                'limit' => '',
                                'offset' => '',
                                'exclusive' => '',
                                'finderQuery' => '',
                                'counterQuery' => ''
            ),
            'InvoicesTimecardReminderLog' => array('className' => 'InvoicesTimecardReminderLog',
                                'foreignKey' => 'invoice_id',
                                'dependent' => true,
                                'conditions' => '',
                                'fields' => '',
                                'order' => '',
                                'limit' => '',
                                'offset' => '',
                                'exclusive' => '',
                                'finderQuery' => '',
                                'counterQuery' => ''
            ),
            'InvoicesPostLog' => array('className' => 'InvoicesPostLog',
                                'foreignKey' => 'invoice_id',
                                'dependent' => true,
                                'conditions' => '',
                                'fields' => '',
                                'order' => '',
                                'limit' => '',
                                'offset' => '',
                                'exclusive' => '',
                                'finderQuery' => '',
                                'counterQuery' => ''
            )
    );

    function determineClearStatus($invoice_id)   
    {
        $this->unbindModel(array('hasMany' => array(
                                                'InvoicesItem',
                                                'EmployeesPayment',
                                                'InvoicesTimecardReminderLog',
                                                'InvoicesPostLog',
                                    ),),false);
       
        $this->unbindModel(array('belongsTo' => array('ClientsContract'),),false);
        $invoice =  $this->read(null, $invoice_id);
        $paymentTotal = 0;
        foreach ($invoice['InvoicesPayment'] as $payment):
            $paymentTotal += $payment['amount'];
        endforeach;                   
        $balance = $invoice['Invoice']['amount']-$paymentTotal;
        if ($balance != 0)
        {
            $invoice['Invoice']['cleared']= 0;
            $this->save($invoice);
        } else {
            $invoice['Invoice']['cleared']= 1;
            $this->save($invoice);                   
        }
        return true;
    }

    function beforeSave() {
        $this->data['Invoice']['modified_date'] = date('Y-m-d');
        return true;
    }   
    function updateTotal($id = null) {
        if(!$this->set_invoice_clear_status($id)) # trigger to set cleared state, only recalculate if not cleared
        {
            $this->recursive = 1;
            $invoice = $this->read(null, $id);
            $invTotal = 0;
            foreach ($invoice['InvoicesItem'] as $invoiceItem):
               $exprate = $invoice['Invoice']['employerexpenserate'];
               $quantity = $invoiceItem['quantity'];
               $cost = $invoiceItem['cost'];
               $amount = $invoiceItem['amount'];
               $invTotal += $invoiceItem['quantity']*$invoiceItem['amount'];
               $comms = $this->InvoicesItem->InvoicesItemsCommissionsItem->find('all',array('conditions'=>array('invoices_item_id'=> $invoiceItem['id'])));

               foreach ($comms as $comm):
                    $commission_net_b4_employerexp = $amount    *$quantity-$cost*$quantity;
                    $commission_employerexp = $commission_net_b4_employerexp*$exprate;
                    $commission_undivided = $commission_net_b4_employerexp-$commission_employerexp;
                    $percent = $comm['InvoicesItemsCommissionsItem']['percent'];
                    $comm['InvoicesItemsCommissionsItem']['amount'] = round($commission_undivided *$percent/100,2);
                    if(!$comm['InvoicesItemsCommissionsItem']['commissions_report_id'] || !$comm['InvoicesItemsCommissionsItem']['cleared'])
                    {
                        $this->InvoicesItem->InvoicesItemsCommissionsItem->save($comm);
                    //debug($invoiceItem);debug($comm);exit;
                    }//debug($comm);exit;
                endforeach;
            endforeach;
            $invoice['Invoice']['amount']= $invTotal;
            //debug($invoice) ;exit;
            $this->save($invoice['Invoice']);
        }
    }

    function updateTotalPrepost($id = null) {
        $this->recursive = 1;
        $invoice = $this->read(null, $id);
        $invTotal = 0;
        foreach ($invoice['InvoicesItem'] as $invoiceItem):
           $exprate = $invoice['Invoice']['employerexpenserate'];
           $quantity = $invoiceItem['quantity'];
           $cost = $invoiceItem['cost'];
           $amount = $invoiceItem['amount'];
           $invTotal += $invoiceItem['quantity']*$invoiceItem['amount'];
           $comms = $this->InvoicesItem->InvoicesItemsCommissionsItem->find('all',array('conditions'=>array('invoices_item_id'=> $invoiceItem['id'])));
            foreach ($comms as $comm):
                $commission_net_b4_employerexp = $amount    *$quantity-$cost*$quantity;
                $commission_employerexp = $commission_net_b4_employerexp*$exprate;
                $commission_undivided = $commission_net_b4_employerexp-$commission_employerexp;
                $percent = $comm['InvoicesItemsCommissionsItem']['percent'];
                $comm['InvoicesItemsCommissionsItem']['amount'] = round($commission_undivided *$percent/100,2);
                if(!$comm['InvoicesItemsCommissionsItem']['commissions_report_id'])
                    $this->InvoicesItem->InvoicesItemsCommissionsItem->save($comm);
            endforeach;
        endforeach;
        $invoice['Invoice']['amount']= $invTotal;
        //debug($invoice) ;exit;
        $this->save($invoice['Invoice']);
    }
   
       function generatepdf($id,$display= null)
       {
           if (!$id) {
            $this->Session->setFlash(__('Invalid Invoice.', true));
            $this->redirect(array('action'=>'index'));
        }
        $this->updateTotalPrepost($id);
        $invoice = $this->read(null, $id);
        $this->set('invoice', $invoice);
        // Contract
        $this->ClientsContract->unbindModel(array('hasMany' => array('Invoice','ContractsItem'),),false);
        $contract =  $this->ClientsContract->read(null, $invoice['Invoice']['contract_id']);
        $client_id=$contract['ClientsContract']['client_id'];
        $employee_id=$contract['ClientsContract']['employee_id'];
        //debug($invoice['Invoice']['contract_id']);
        // Client
        $this->ClientsContract->Client->unbindModel(array('hasMany' => array('ClientsContract','ClientsManager','ClientsMemo','ClientsSearch'),),false);
        $client =  $this->ClientsContract->Client->read(null, $client_id); //debug($client);exit;
        // Employee
        $this->ClientsContract->Employee->unbindModel(array('hasMany' => array('ClientsContract','EmployeesMemo','EmployeesPayment'),),false);
        $employee =  $this->ClientsContract->Employee->read(null, $employee_id);
       
         App::import('Vendor','fpdf/fpdf');
           $datearray = explode('-',$invoice['Invoice']['date']);
       
           $pdf=new FPDF();
          
        $pdf->AddPage();
        $pdf->Image('img/RRG_LOGO_WEB.jpg',10,8,33,'JPEG');
        $pdf->SetFont('Arial','B',16);
        $pdf->Cell(140,10,'',0,0);
        $pdf->Cell(40,10,'Invoice '.$invoice['Invoice']['id'],0,1);
        $pdf->Cell(140,10,'',0,0);
        $pdf->Cell(30,10,'Date: '.$datearray[1].'/'.$datearray[2].'/'.$datearray[0],0,1);
       
        $pdf->Cell(40,15,'',0,1);
        $pdf->Cell(100,7,'ROCKETS REDGLARE',0,0);
        $pdf->Cell(40,7,substr(strtoupper  ($client['Client']['name']),0,40),0,1);
       
       
        //$pdf->Cell(40,15,'','B',1);
        $pdf->Cell(100,7,'1082 VIEW WAY',0,0);
        $pdf->Cell(40,7,substr(strtoupper  ($client['Client']['street1']),0,40),0,1);
       
        //$pdf->Cell(40,15,'','B',1);
        $pdf->Cell(100,7,'PACIFICA, CA 94044',0,0);
        $pdf->Cell(40,7,
        substr(strtoupper  ($client['Client']['city']),0,40).', '.
        substr(strtoupper  ($client['State']['post_ab']),0,40).' '.
        substr(strtoupper  ($client['Client']['zip']),0,40)
        ,0,1);
        $pdf->Cell(40,10,'',0,1);
        $pdf->Cell(40,7,'Terms: NET '.$invoice['Invoice']['terms'],0,1);
        $datearray = date_parse($invoice['Invoice']['date']);
        $duedate  = mktime(0, 0, 0, $datearray['month']  , $datearray['day']+$invoice['Invoice']['terms'], $datearray['year']);
        $today  = mktime(0, 0, 0, date("m")  , date("d"), date("Y"));
        $invoice['Invoice']['duedate'] = date('Y-m-d',$duedate);
        $datearray = date_parse($invoice['Invoice']['duedate']);
        $pdf->Cell(40,7,'Due Date: '.$datearray['month'].'/'.$datearray['day'].'/'.$datearray['year'],0,1);
       
        $startdatearray = explode('-',$invoice['Invoice']['period_start']);
        $enddatearray = explode('-',$invoice['Invoice']['period_end']);
       
        $pdf->Cell(40,15,'',0,1);
        $pdf->MultiCell(180,7,$invoice['ClientsContract']['title'],0,1);
       
        $pdf->Cell(40,15,'',0,1);
        $pdf->Cell(180,7,'During the period of: '.$startdatearray[1].'/'.$startdatearray[2].'/'.$startdatearray[0].' to '.$enddatearray[1].'/'.$enddatearray[2].'/'.$enddatearray[0].'.',0,1);
        $pdf->Cell(40,15,'',0,1);
        $pdf->Cell(20,7,'',0,0);
        $pdf->Cell(90,7,'Description','B',0,'C');
        $pdf->Cell(20,7,'Quantity','B',0,'C');
        $pdf->Cell(20,7,'Cost','B',0,'R');
        $pdf->Cell(35,7,'Subtotal','B',1,'R');

        $i = 0;
        $totalQuant = 0;
        foreach ($invoice['InvoicesItem'] as $InvoiceItem):
            $class = null;
            if ($i++ % 2 == 0) {
                $class = ' class="altrow"';
            }
            $totalQuant += $InvoiceItem['quantity'];
            $quant = sprintf("%8.2f",    $InvoiceItem['quantity']); // right-justification with spaces
            $cost = sprintf("%8.2f",    $InvoiceItem['amount']); // right-justification with spaces
            $subtotal = sprintf("%8.2f",    $InvoiceItem['quantity']*$InvoiceItem['amount']); // right-justification with spaces
            if ($subtotal != 0){
                $pdf->Cell(20,7,'',0,0);
                $pdf->Cell(90,7,$InvoiceItem['description'],'B',0);
                $pdf->Cell(20,7,$quant,'B',0,'R');
                $pdf->Cell(20,7,$cost,'B',0,'R');
                $pdf->Cell(35,7,$subtotal,'B',1,'R');
            }
        endforeach;
        $pdf->Cell(20,7,'',0,0);
        $pdf->Cell(90,7,'',0,0);
        $pdf->Cell(20,7,sprintf("%8.2f",$totalQuant),0,0,'R');
        $pdf->Cell(20,7,'',0,0,'R');
        $pdf->Cell(35,7,sprintf("%8.2f",$invoice['Invoice']['amount']),0,1,'R');
           
        $pdf->Ln();
        $pdf->MultiCell(180,7,$invoice['ClientsContract']['invoicemessage'],0,1);
        $pdf->Ln();
        $pdf->MultiCell(180,7,$invoice['Invoice']['message'],0,1);
        $filename = 'rocketsredglare_invoice_'.$invoice['Invoice']['id'].'_'.$employee['Employee']['firstname'].'_'.$employee['Employee']['lastname'].'_'.$invoice['Invoice']['period_start'].'_to_'.$invoice['Invoice']['period_end'].'.pdf';
        $pdf->Output('rrg_invoices/'.$filename,'F');
        if ($display!= null)
            $pdf->Output();
       
        return $pdf;
       }
    function timecard($id=null) 
    {
        if (!$id && empty($this->data)) {
            $this->Session->setFlash(__('Invalid Invoice', true));
            $this->redirect(array('action'=>'index'));
        }
        $inv = $this->read(null, $id);
        $inv['Invoice']['timecard']   = 1;
        $this->save($inv);
        return true;
    }
    function void($id=null) 
    {
        if (!$id && empty($this->data)) {
            $this->Session->setFlash(__('Invalid Invoice', true));
            $this->redirect(array('action'=>'index'));
        }
        $inv = $this->read(null, $id);
        $inv['Invoice']['voided']   = 1;
        $this->save($inv);
        return true;
    }

    #
    # re-checks status of invoice
    #
    function set_invoice_clear_status($id = null) {
        $this->recursive = 2;
        $this->bindModel(array('hasMany' => array('InvoicesPayment','InvoicesItem',),),false);
        $invoice = $this->read(null, $id);
        //debug($invoice);exit;
       
        if(isset($invoice['InvoicesItem']))
        {
            $invoice['Invoice']['amount'] = 0;
            foreach ($invoice['InvoicesItem'] as $item)
            {
                $invoice['Invoice']['amount'] += $item['quantity']*$item['amount'];
            }
            //debug($invoice);exit;
            $this->save($invoice);
        }
        if($invoice['Invoice']['posted'])
        {
            //$this->unbindModel(array('hasMany' => array('InvoicesPayment',),),false);       
            $balance = $invoice['Invoice']['amount']; //debug($id);debug($invoice);exit;
            if(isset($invoice['InvoicesPayment']))
            {           
                foreach ($invoice['InvoicesPayment'] as $pay):
                    $balance -= $pay['amount'];
                endforeach;    
            }   
            //debug(abs($balance));
            if (abs($balance) < .01)
            {
                $invoice['Invoice']['cleared'] = 1;
                $this->save($invoice); //debug($invoice);exit;
            }
        }
        return $invoice['Invoice']['cleared'];
    }

    function rebuild_invoice($id = null)
    {
        $this->recursive = 2;
        $this->ClientsContract->unbindModel(array('belongsTo' => array('Employee','Client','State','Period'),),false);
        $this->ClientsContract->unbindModel(array('hasMany' => array('Invoice','ClientsManager'),),false);
        $invoice = $this->read(null, $id);
        # delete old items
        foreach ($invoice['InvoicesItem'] as $item):
                $this->InvoicesItem->del($item['id']);
        endforeach;   
        # setup new items
        foreach ($invoice['ClientsContract']['ContractsItem'] as $item):
            if($item['active'] == 1)       
            {           
                $itemResult = $this->ClientsContract->ContractsItem->read(null, $item['id']);
                $invoicelineitem= array();
                $invoicelineitem['InvoicesItem']['description'] = $item['description'];
                $invoicelineitem['InvoicesItem']['amount'] = $item['amt'];
                $invoicelineitem['InvoicesItem']['cost'] = $item['cost'];
                $invoicelineitem['InvoicesItem']['invoice_id'] = $id;
                $this->InvoicesItem->create();
                $this->InvoicesItem->save($invoicelineitem);
                foreach ($itemResult['ContractsItemsCommissionsItem'] as $citem):
                        $invlineitemcommslineitem = array();
                        $invlineitemcommslineitem['InvoicesItemsCommissionsItem']['employee_id'] = $citem['employee_id'];
                        $invlineitemcommslineitem['InvoicesItemsCommissionsItem']['percent'] = $citem['percent'];
                        $invlineitemcommslineitem['InvoicesItemsCommissionsItem']['invoices_item_id'] =
                                        $this->InvoicesItem->getLastInsertID();
                        $this->InvoicesItem->InvoicesItemsCommissionsItem->create();
                        $this->InvoicesItem->InvoicesItemsCommissionsItem->save($invlineitemcommslineitem);
                endforeach;   
            }                                       
        endforeach;       
        return true;
    }
    // add invoice from javascript application
    function add_dynamic($formdata,$session) {
        if (!empty($formdata)) {
            $month= date('m'); $day= date('d');    $year= date('Y');$hour= date('h');    $min= date('i');$meridian=date('a');
            $currentTime=array('month'=>$month,    'day' => $day,'year' => $year,'hour' => $hour,'min' => $min,'meridian' => $meridian);
            // Fill in new invoice looked up values from Clients Contracts
            $this->ClientsContract->unbindModel(array('hasMany' => array('Invoice'),),false);
            $clientsContract = $this->ClientsContract->find('all',
            array('conditions'=>array('ClientsContract.id'=>$formdata['Invoice']['contract_id'])));
            //debug($clientsContract[0]['ClientsContract']);
   
            $formdata['Invoice']['terms'] = $clientsContract[0]['ClientsContract']['terms'];
            $formdata['Invoice']['employerexpenserate'] = $clientsContract[0]['ClientsContract']['employerexp'];
            $formdata['Invoice']['po'] = $clientsContract[0]['ClientsContract']['po'];
            $formdata['Invoice']['date'] = $currentTime;
            $formdata['Invoice']['message'] = 'Please forward to your accounts payable department with the commentary, "APPROVED." Thank you for your business.';
            $formdata['Invoice']['amount'] = 0;
            foreach ($formdata['Item'] as $contractItem):
                $formdata['Invoice']['amount']+=$contractItem['amt']*$contractItem['cost'];
            endforeach;
            $this->create();
            $this->save($formdata);
            $invoiceID = $this->getLastInsertID();
            $session->setFlash(__('Invoice saved.', true));
            $invoiceID = $this->getLastInsertID();
            $this->ClientsContract->ContractsItem->unbindModel(array('hasMany' => array('ContractsItemsCommissionsItem'),),false);
            $this->ClientsContract->ContractsItem->unbindModel(array('belongsTo' => array('ClientsContract'),),false);
            $contractItems = $this->ClientsContract->ContractsItem->find('all',array('conditions'=>array('Contract_id'=>$formdata['Invoice']['contract_id'])));
            foreach ($formdata['Item'] as $contractItem):
                if($contractItem['id']!=99999)
                {
                    $item = array();
                    $this->InvoicesItem->create();
                    $item['InvoicesItem']['invoice_id']=$invoiceID;
                    $item['InvoicesItem']['amount']=$contractItem['amt'];
                    $item['InvoicesItem']['cost']=$contractItem['cost'];
                    $item['InvoicesItem']['quantity']=$contractItem['quantity'];
                    $item['InvoicesItem']['description']=$contractItem['description'];
                    $this->InvoicesItem->save($item);
                    $itemID = $this->InvoicesItem->getLastInsertID();
                    $this->ClientsContract->ContractsItem->ContractsItemsCommissionsItem->unbindModel(array('belongsTo' => array('Employee','ContractsItems'),),false);
                    $commissionsItems = $this->ClientsContract->ContractsItem->ContractsItemsCommissionsItem->find('all',array('conditions'=>array('contracts_items_id'=>$contractItem['id'])));
                    foreach ($commissionsItems as $commissionsItem):
                        $commissionsItemInsert = array();
                        $this->InvoicesItem->InvoicesItemsCommissionsItem->create();
                        $commissionsItemInsert['InvoicesItemsCommissionsItem']['employee_id']=$commissionsItem['ContractsItemsCommissionsItem']['employee_id'];
                        $commissionsItemInsert['InvoicesItemsCommissionsItem']['invoices_item_id']=$itemID;
                        $commissionsItemInsert['InvoicesItemsCommissionsItem']['percent']=$commissionsItem['ContractsItemsCommissionsItem']['percent'];
                        $this->InvoicesItem->InvoicesItemsCommissionsItem->save($commissionsItemInsert);
                    endforeach;
                }
            endforeach;
            return $invoiceID;
        }
        return false;
    }
    function save_dynamic($formdata,$session) {
        if (!empty($formdata)) {
            $month= date('m'); $day= date('d');    $year= date('Y');$hour= date('h');    $min= date('i');$meridian=date('a');
            $currentTime=array('month'=>$month,    'day' => $day,'year' => $year,'hour' => $hour,'min' => $min,'meridian' => $meridian);
            $formdata['Invoice']['amount'] = 0;
            foreach ($formdata['Item'] as $invItem):
                $formdata['Invoice']['amount']+=$invItem['amount']*$invItem['cost'];
            endforeach;
            $this->save($formdata);
            $session->setFlash(__('Invoice saved.', true));
            foreach ($formdata['Item'] as $invItem):
                $item = array();
                $item['InvoicesItem']['invoice_id']=$formdata['Invoice']['id'];
                $item['InvoicesItem']['id']=$invItem['id'];
                $item['InvoicesItem']['amount']=$invItem['amount'];
                $item['InvoicesItem']['cost']=$invItem['cost'];
                $item['InvoicesItem']['quantity']=$invItem['quantity'];
                $item['InvoicesItem']['description']=$invItem['description'];
                $this->InvoicesItem->save($item);
            endforeach;
            return $formdata['Invoice']['id'];
        }
        return false;
    }
    function getInvoiceReview($id)   
    {
        $this->updateTotal($id);
        $result = $this->read(null,$id);
        $this->ClientsContract->Employee->unbindModel(array('hasMany' => array('ClientsContract','EmployeesPayment','EmployeesMemo','EmployeesEmail'),),false);
        $this->ClientsContract->Client->unbindModel(array('hasMany' => array('ClientsContract','ClientsManager','ClientsMemo','ClientsCheck','ClientsSearch'),),false);
        $result['Employee'] = $this->ClientsContract->Employee->read(null,$result['ClientsContract']['employee_id']);
        $result['Client'] = $this->ClientsContract->Client->read(null,$result['ClientsContract']['client_id']);
        $resutl['InvoiceItems'] = array();
        foreach($this->data['InvoicesItem'] as $item)
        {
            $result['InvoiceItems'][] = $this->InvoicesItem->read(null, $item['id']);
        }//debug($result);exit;
        return $result;
    }
    function open_invoices()
    {
        App::import('Component', 'DateFunction');
        $dateF = new DateFunctionComponent();
        $this->recursive = 2;
        $this->ClientsContract->unbindModel(array('hasAndBelongsToMany' => array('ClientsManager'),),false);
        $this->ClientsContract->unbindModel(array('hasMany' => array('Invoice','ContractsItem'),),false);
        $this->unbindModel(array('hasMany' => array('InvoicesItem','InvoicesPayment','EmployeesPayment','InvoicesTimecardReminderLog','InvoicesPostLog'
        ,'ClientsManager'),),false);       
        $open = $this->find('all', array(
                                                'conditions'=>array('voided'=>0,
                                                                    'cleared'=>0,
                                                                    'amount >0'),
                                                        'fields'=>array(
                                                        'Invoice.id',
                                                        'Invoice.period_start',
                                                        'Invoice.period_end',
                                                        'Invoice.date',
                                                        'Invoice.terms',
                                                        'Invoice.notes',
                                                        'Invoice.amount',
                                                        'Invoice.contract_id',
                                                        'ClientsContract.employee_id'),
                                                'order'=>array('Invoice.date DESC')
                                                        )
                                                    );
        //debug($open);exit;
        $count = 0;
        foreach ($open as $invoice):
            $datearray = date_parse($invoice['Invoice']['date']);
            $duedate  = mktime(0, 0, 0, $datearray['month']  , $datearray['day']+$invoice['Invoice']['terms'], $datearray['year']);
            $today  = mktime(0, 0, 0, date("m")  , date("d"), date("Y"));
            $open[$count]['Invoice']['duedate'] = date('Y-m-d',$duedate);
            $open[$count]['Invoice']['dayspast'] = $dateF->dateDiff(date('Y-m-d',$duedate),date('Y-m-d',$today));
            if ($duedate < mktime(0, 0, 0, date("m")  , date("d"), date("Y")))
            {
                $open[$count]['Invoice']['pastdue'] = 1;
            } else
            {
                $open[$count]['Invoice']['pastdue'] = 0;
            }//debug($open[$count]['Invoice']); exit;
            $count++;
        endforeach;
        return $open;
    }
    function cleared_invoices()
    {
        $this->ClientsContract->unbindModel(array('hasAndBelongsToMany' => array('ClientsManager'),),false);
        $this->ClientsContract->unbindModel(array('hasMany' => array('Invoice','ContractsItem'),),false);
        $this->unbindModel(array('hasMany' => array('InvoicesItem','InvoicesPayment','EmployeesPayment','InvoicesTimecardReminderLog','InvoicesPostLog'
        ,'ClientsManager'),),false);       
        $cleared = $this->find('all', array(
                                                'conditions'=>array('voided'=>0,'posted'=>1,'cleared'=>1),
                                                        'fields'=>array(
                                                        'Invoice.id',
                                                        'Invoice.period_start',
                                                        'Invoice.period_end',
                                                        'Invoice.date',
                                                        'Invoice.terms',
                                                        'Invoice.notes',
                                                        'Invoice.amount',
                                                        'Invoice.contract_id',
                                                        'ClientsContract.employee_id'),
                                                'order'=>array('Invoice.date ASC'),
                                                        )
                                                    );
        $count = 0;
        foreach ($cleared as $invoice):
            $datearray = date_parse($invoice['Invoice']['date']);
            $duedate  = mktime(0, 0, 0, $datearray['month']  , $datearray['day']+$invoice['Invoice']['terms'], $datearray['year']);
            $today  = mktime(0, 0, 0, date("m")  , date("d"), date("Y"));
            $cleared[$count]['Invoice']['duedate'] = date('Y-m-d',$duedate);
            $count++;
        endforeach;
        $this->ClientsContract->unbindModel(array('hasAndBelongsToMany' => array('ClientsManager'),),false);
        $this->ClientsContract->unbindModel(array('hasMany' => array('Invoice','ContractsItem'),),false);
        $this->unbindModel(array('hasMany' => array('InvoicesItem','InvoicesPayment','EmployeesPayment','InvoicesTimecardReminderLog','InvoicesPostLog'
        ,'ClientsManager'),),false);       
        return $cleared;
    }
    function voided_invoices()
    {
        $this->recursive = 1;
        $voided = $this->find('all', array(
                                                'conditions'=>array('voided'=>1),
                                                        'fields'=>array(
                                                        'Invoice.id',
                                                        'Invoice.period_start',
                                                        'Invoice.period_end',
                                                        'Invoice.date',
                                                        'Invoice.amount',
                                                        'Invoice.notes',
                                                        'Invoice.contract_id',
                                                        'ClientsContract.employee_id'),
                                                'order'=>array('Invoice.date DESC')
                                                        )
                                                    ); //debug($voided);exit;
        return $voided;
    }
}
?>