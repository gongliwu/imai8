<?php
	
namespace app\mobile\controller;
use think\db;

class Integral extends MobileBase {
	
	public function integral(){
		return $this->fetch();
	}
	
}
?>