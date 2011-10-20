<?php
class Mage_OrderExport_Model_System_Config_Source_Status
{
    /**
     * Prepare order status with postfinance
     *
     * @return array
     */
    public function toOptionArray()
    {
    	$array = array();
		
		// Load the default order status array
    	$mod = Mage::getModel('adminhtml/system_config_source_order_status');
		if($mod) {
			$array = $mod->toOptionArray();
		}
		
		// extend with our needed status only if module postfinance is active
		$modules = Mage::getConfig()->getNode('modules')->children();
		$modulesArray = (array)$modules;
		if($modulesArray['Mage_Postfinance']->is('active')) {
			$array[] = array('value' => 'pending_postfinance', 'label' => Mage::helper('postfinance')->__('Pending PostFinance'));
		}
		return $array;
    }
}
?>