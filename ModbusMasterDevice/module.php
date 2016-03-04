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
                        $this->RegisterVariableBoolean("Value", "Wert", "~Switch");
                        $this->DisableAction("Value");
                    }
                    else
                    {
                        $this->RegisterVariableBoolean("Value", "Wert", "~Switch");
                        $this->EnableAction("Value");
                    }
                break;    
                case 7: case 9:           
                    if ( $this->ReadPropertyBoolean("ReadOnly") )
                    {
                        $this->RegisterVariableFloat("Value", "Wert");
                        $this->DisableAction("Value");
                    }
                    else
                    {
                        $this->RegisterVariableFloat("Value", "Wert");
                        $this->EnableAction("Value");
                    }
                break;
                default:
                    if ( $this->ReadPropertyBoolean("ReadOnly") )
                    {
                        $this->RegisterVariableInteger("Value", "Wert");
                        $this->DisableAction("Value");
                    }
                    else
                    {
                        $this->RegisterVariableInteger("Value", "Wert", "Intensity.32767");
                        $this->EnableAction("Value");
                    }                    
                break;    
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
                if ( $this->ReadPropertyBoolean("ReadOnly") )
                {
                    if ($this->ReadPropertyInteger("DataType") === 0) 
                    {   
                        $Value = @$Data["FC2"][$this->ReadPropertyInteger("Address")];
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
                        $Bytes = @$Data["FC4"][$this->ReadPropertyInteger("Address")];
                        if(isset($Bytes))
                        {
                            switch ($this->ReadPropertyInteger("DataType")) 
                            {
                                case 1: case 2: case 3:
                                    $Value = PhpType::bytes2unsignedInt($Bytes);
                                    if ( GetValue($this->GetIDForIdent("Value")) <> $Value )
                                    {
                                        SetValueInteger($this->GetIDForIdent("Value"), $Value);
                                    }
                                break;   
                                case 4: case 5: case 6: case 8:
                                    $Value = PhpType::bytes2signedInt($Bytes);
                                    if ( GetValue($this->GetIDForIdent("Value")) <> $Value )
                                    {
                                        SetValueInteger($this->GetIDForIdent("Value"), $Value);
                                    }
                                break;  
                                case 7: case 9:
                                    $Value = PhpType::bytes2float($Bytes);
                                    if ( GetValue($this->GetIDForIdent("Value")) <> $Value )
                                    {
                                        SetValueFloat($this->GetIDForIdent("Value"), $Value);
                                    }
                                break;                                                             
                            }   
                        } 
                    }
                }
                else
                {
                    if ($this->ReadPropertyInteger("DataType") === 0) 
                    {   
                        $Value = @$Data["FC1"][$this->ReadPropertyInteger("Address")];
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
                        $Bytes = @$Data["FC3"][$this->ReadPropertyInteger("Address")];
                        if(isset($Bytes))
                        {
                            switch ($this->ReadPropertyInteger("DataType")) 
                            {
                                case 1: case 2: case 3:
                                    $Value = PhpType::bytes2unsignedInt($Bytes);
                                    if ( GetValue($this->GetIDForIdent("Value")) <> $Value )
                                    {
                                        SetValueInteger($this->GetIDForIdent("Value"), $Value);
                                    }
                                break;   
                                case 4: case 5: case 6: case 8:
                                    $Value = PhpType::bytes2signedInt($Bytes);
                                    if ( GetValue($this->GetIDForIdent("Value")) <> $Value )
                                    {
                                        SetValueInteger($this->GetIDForIdent("Value"), $Value);
                                    }
                                break;  
                                case 7: case 9:
                                    $Value = PhpType::bytes2float($Bytes);
                                    if ( GetValue($this->GetIDForIdent("Value")) <> $Value )
                                    {
                                        SetValueFloat($this->GetIDForIdent("Value"), $Value);
                                    }
                                break;                                                                     
                            }
                        }    
                    }                       
                }                                         
            }
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