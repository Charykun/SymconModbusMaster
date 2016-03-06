<?php                                                                           
    class ModBusMasterDevice extends IPSModule                                  
    {
        /**
         * Log Message
         * @param string $Message
         */
        protected function Log($Message)
        {
            IPS_LogMessage(__CLASS__, $Message);
        }

        /**
         * Create
         */         
        public function Create()
        {
            //Never delete this line!
            parent::Create();   
            
            $this->RegisterPropertyInteger("DataType", 0);
            $this->RegisterPropertyInteger("Address", 0);
            $this->RegisterPropertyBoolean("ReadOnly", false);
            $this->RegisterPropertyString("Math", "");
            $this->RegisterPropertyInteger("SwitchDuration", 0);
            
            // Connect to IO or create it
            $this->ConnectParent("{13D6E3BC-9C30-4698-995F-4E566590CD84}");
        }

        /**
         * ApplyChanges
         */
        public function ApplyChanges()
        {
            //Never delete this line!
            parent::ApplyChanges();   
            
            switch ($this->ReadPropertyInteger("DataType")) 
            {
                case 0:
                    if ( $this->ReadPropertyBoolean("ReadOnly") )
                    {
                        $this->RegisterVariableBoolean("Value", "Value", "~Switch");
                        $this->DisableAction("Value");
                    }
                    else
                    {
                        $this->RegisterVariableBoolean("Value", "Value", "~Switch");
                        $this->EnableAction("Value");
                    }
                break;    
                case 7: case 9:           
                    if ( $this->ReadPropertyBoolean("ReadOnly") )
                    {
                        $this->RegisterVariableFloat("Value", "Value");
                        $this->DisableAction("Value");
                    }
                    else
                    {
                        $this->RegisterVariableFloat("Value", "Value");
                        $this->EnableAction("Value");
                    }
                break;
                default:
                    if ( $this->ReadPropertyBoolean("ReadOnly") )
                    {
                        $this->RegisterVariableInteger("Value", "Value");
                        $this->DisableAction("Value");
                    }
                    else
                    {
                        $this->RegisterVariableInteger("Value", "Value", "Intensity.32767");
                        $this->EnableAction("Value");
                    }                    
                break;    
            }
            if( ($this->ReadPropertyString("Math") != "") and ($this->ReadPropertyInteger("DataType") != 0) and $this->ReadPropertyBoolean("ReadOnly"))
            {
                $this->RegisterVariableFloat("ValueMath", "Result");
            }
            else
            {
                $this->UnregisterVariable("ValueMath");
            }
        }      
        
        /**
         * RequestAction
         * @param string $Ident
         * @param type $Value
         */
        public function RequestAction($Ident, $Value)
        {
            switch ($Ident) 
            {
                case "Value":
                    if ( $this->ReadPropertyInteger("DataType") === 0 )
                    {
                        $this->WriteCoil($Value);                        
                    }
                    else 
                    {
                        $this->WriteRegister($Value);                        
                    }
                break;
            }
        }
        
        /**
         * ReceiveData
         * @param string $JSONString
         */
        public function ReceiveData($JSONString) 
        {
            // Empfangene Daten
            $Data = json_decode($JSONString);
            //IPS_LogMessage("ReceiveData", utf8_decode($JSONString));  
            if ( $Data->DataID === "{449015FB-6717-4BB6-9F95-F69945CE1272}" )
            {
                $Data = json_decode($Data->Buffer, true);
                if ($this->ReadPropertyInteger("DataType") === 0)
                {
                    if ( $this->ReadPropertyBoolean("ReadOnly") )
                    {
                        $Value = @$Data["FC2"][$this->ReadPropertyInteger("Address")];                        
                    }
                    else
                    {
                        $Value = @$Data["FC1"][$this->ReadPropertyInteger("Address")];                        
                    }
                    if(isset($Value))
                    {
                        if ( GetValue($this->GetIDForIdent("Value")) <> $Value )
                        {
                            SetValueBoolean($this->GetIDForIdent("Value"), $Value);
                        }
                    }                   
                }
                else 
                {
                    if ( $this->ReadPropertyBoolean("ReadOnly") )
                    {
                        $Bytes = @$Data["FC4"][$this->ReadPropertyInteger("Address")];                        
                    }
                    else
                    {
                        $Bytes = @$Data["FC3"][$this->ReadPropertyInteger("Address")];                        
                    }                   
                    if(isset($Bytes))
                    {
                        switch ($this->ReadPropertyInteger("DataType")) 
                        {
                            case 1: case 2: case 3:
                                $Value = PhpType::bytes2unsignedInt($Bytes);
                            break;   
                            case 4: case 5: case 6: case 8:
                                $Value = PhpType::bytes2signedInt($Bytes);
                            break;  
                            case 7: case 9:
                                $Value = PhpType::bytes2float($Bytes);                                
                            break;                                                             
                        }
                        if ( GetValue($this->GetIDForIdent("Value")) <> $Value )
                        {
                            SetValue($this->GetIDForIdent("Value"), $Value);
                        }                         
                        if( ($this->ReadPropertyString("Math") != "") and $this->ReadPropertyBoolean("ReadOnly"))
                        {                            
                            $Value = $this->Math($Value . $this->ReadPropertyString("Math"));
                            if ( GetValue($this->GetIDForIdent("ValueMath")) <> $Value )
                            {
                                SetValue($this->GetIDForIdent("ValueMath"), $Value);
                            }
                        }                        
                                            
                    }                    
                }            
            }
        }
        
        private function Math($Str)
        {
            $v=array();
            $v[0]=$v[1]=$op=null;
            if(preg_match_all('#[+*/^-]|\-?[\d,.]+#',$Str,$m))
            {
                foreach($m[0] as $tk)
                {
                    switch($tk)
                    {
                        case '+': case '-': case '*': case '/': case '^': $op=$tk; break;
                        default:
                            $v[is_null($op) && is_null($v[0])?0:1]=$tk;
                            if(!is_null($v[1]))
                            {
                                switch($op)
                                {
                                    case '+': $v[0] = $v[0] + $v[1]; break;
                                    case '-': $v[0] = $v[0] - $v[1]; break;
                                    case '*': $v[0] = $v[0] * $v[1]; break;
                                    case '/': $v[0] = $v[0] / $v[1]; break;
                                    case '^': $v[0] = $v[0] ^ $v[1]; break;
                                }
                            }
                        break;
                    }
                }
            }
            return $v[0];
        }


        /**
         * ModBusMaster_WriteCoil
         * @param boolean $Value
         * @return boolean
         */
        public function WriteCoil($Value) 
        {
            if ($this->ReadPropertyBoolean("ReadOnly"))
            {
                trigger_error("Address is marked as read-only!", E_USER_WARNING);
                return;
            }            
            if ($this->ReadPropertyInteger("DataType") === 0)
            {       
                $resultat = $this->SendDataToParent(json_encode(Array("DataID" => "{A3419A88-C83B-49D7-8706-D3AFD596DFBB}", "FC" => "5", "Address" => $this->ReadPropertyInteger("Address"), "Data" => $Value))); 
                if($this->ReadPropertyInteger("SwitchDuration") > 0)
                {
                    $this->RegisterTimer("Poller", 0, "ModBusMaster_WriteCoil(\$_IPS['TARGET'], false);IPS_SetEventActive(\$_IPS['EVENT'],false);"); 
                    $this->SetTimerInterval("Poller", $this->ReadPropertyInteger("SwitchDuration") * 1000);
                }
                return $resultat;
            }
            else 
            {
                trigger_error("Invalid DataType!", E_USER_WARNING);
            }
        }
        
        
        /**
         * ModBusMaster_WriteRegister
         * @param integer $Value
         * @return boolean
         */
        public function WriteRegister($Value) 
        {
            if ($this->ReadPropertyBoolean("ReadOnly"))
            {
                trigger_error("Address is marked as read-only!", E_USER_WARNING);
                return;
            }            
            if ($this->ReadPropertyInteger("DataType") === 0)
            { 
                trigger_error("Invalid DataType!", E_USER_WARNING);
            }
            else 
            {
                $resultat = $this->SendDataToParent(json_encode(Array("DataID" => "{A3419A88-C83B-49D7-8706-D3AFD596DFBB}", "FC" => "6", "Address" => $this->ReadPropertyInteger("Address"), "Data" => $Value)));  
                return $resultat;               
            }
        }       
        
    }