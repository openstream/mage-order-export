<?php
class Mage_OrderExport_Model_Observer 
{
	/**
	 * Observer called from cronjob to check if there are orders which have a specific status (BE config) between
	 * yesterday 13.30 and today 13.30.
	 * - Generate CSV file (def. in /var/export)
	 * - Send via email
	 * @author 	Manuel Neukum
	 * @param	$observer	Observer object
	 */
	public function check($observer) 
	{
		Mage::log("Checking for new Orders", null, 'orderexport.log');
		// Load where to store the file
		$path = Mage::getStoreConfig('sales/export/path');
		if(empty($path)) {
			$path = 'var/report';
		}
		
		// Load the status description from the config
    	$name_of_receiver = Mage::getStoreConfig('sales/export/recname');
		$mail_of_receiver = Mage::getStoreConfig('sales/export/recmail');
		if(empty($mail_of_receiver)) {
			$name_of_receiver = Mage::getStoreConfig('trans_email/ident_general/name');
			$mail_of_receiver = Mage::getStoreConfig('trans_email/ident_general/email');
		}
		
		// Load Status
		$filter_status = Mage::getStoreConfig('sales/export/filter_status');
		if(empty($filter_status)) {
			$filter_status = 'pending';
		}
		
    	// Load the order collection with specified data
    	$collection = Mage::getModel('sales/order')->getCollection();
		$collection->addAttributeToSelect('entity_id');
		$collection->addAttributeToSelect('increment_id');
		$collection->addAttributeToSelect('created_at');
		$collection->addAttributeToSelect('billing_name');
		$collection->addAttributeToSelect('shipping_name');
		$collection->addAttributeToSelect('status');
		$collection->addAttributeToSelect('*');

		// Define time period
		$yesterday 	= date('Y-m-d',strtotime('-1 days')).' 13:30:00';
        $today 		= date('Y-m-d').' 13:30:00';

		// and filter from yesterday 13.30 till today 13.30 and the status from the BE ( def pending )
		$collection->addAttributeToFilter('created_at', array("from" =>  $yesterday, "to" =>  $today, "datetime" => true));
		$collection->addAttributeToFilter('status', $filter_status);

		// only export if we have new orders
		if($collection->count() > 0) {
			// prepare Header
			$content = "Bestellnummer,Bestellt am,Rechnung an,Versandname,Status\n";
            try {
            	$i=0;
				
				// Load the Data and Address for every order
                foreach ($collection as $order) {
                	$loadedOrder = Mage::getModel('sales/order')->load($order->getId());
                    $content .= $loadedOrder->getIncrementId(). ',';
                    $content .= $loadedOrder->getCreatedAt(). ',';
                    $content .= $loadedOrder->getBillingAddress()->getName(). ',';
                    $content .= $loadedOrder->getShippingAddress()->getName(). ',';
                    $content .= $loadedOrder->getStatus()."\n";
					$i++;                
				}
				
				// Show total 
				$content .= ",,,,\n";
				$content .= "Anzahl:,$i,,,\n";			

				// Write in File
				$date = new Zend_Date($today);
				$filename = "$path/orderexport__".$date->toString('dd_MM_yyyy').".csv"; 
				       
				// is folder writeable
				if (is_writable( getcwd() )) {
			       	$fp = fopen($filename, 'w');
					fwrite($fp, $content);
					fclose($fp);
					Mage::log("$i order(s) in $filename successfully exported!!", null, 'orderexport.log');

					// ### now we want to send the new file as email ###
					$mail = new Zend_Mail();
            		$mail->setBodyText('siehe Anhang');
					// Get the data from the store config (owner)
        			$mail->setFrom(Mage::getStoreConfig('trans_email/ident_general/email'), Mage::getStoreConfig('trans_email/ident_general/name'));
					// Get the data from the orderexport config
					$mail->addTo($mail_of_receiver, $name_of_receiver);
					$mail->setSubject("Exportierte Bestellungen vom $yesterday - $today");

					// Add the file as attachment
					$att = $mail->createAttachment(file_get_contents($filename));
					$att->type        = 'text/csv';
					$att->disposition = Zend_Mime::DISPOSITION_INLINE;
					$att->encoding    = Zend_Mime::ENCODING_BASE64;
					$att->filename    = $filename;
            		
					// Send
        			$mail->send();
					Mage::log("Sending Mail to $mail_of_receiver", null, 'orderexport.log');
				} else {
				    Mage::log('No write permission in folder', null, 'orderexport.log');
				}
            } catch (Exception $e) {
                Mage::log('Exception: '.$e->getMessage(), null, 'orderexport.log');
            }
		} else {
			Mage::log('There are no new orders with your status '.$filter_status, null, 'orderexport.log');
		}
	}
}
?>