<?php
class HeidelpayCD_Edition_Block_Info_Invoice extends Mage_Payment_Block_Info
{
	public function toPdf()
	{
		$this->setTemplate('hcd/info/pdf/invoice.phtml');
		return $this->toHtml();
	}
}