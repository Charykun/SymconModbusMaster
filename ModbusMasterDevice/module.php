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
            
            $this->RegisterPropertyInteger("DataType", 1);
            $this->RegisterPropertyInteger("Address", 0);
            
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
                case 1:
                    $this->RegisterVariableBoolean("Value", "Wert", "~Switch");
                    $this->EnableAction("Value");
                break;
                case 2:
                    $this->RegisterVariableBoolean("Value", "Wert", "~Switch");
                    $this->DisableAction("Value");
                break;
                case 3: 
                    $this->RegisterVariableInteger("Value", "Wert", "Intensity.32767");
                    $this->EnableAction("Value");
                break;
                case 4: 
                    $this->RegisterVariableInteger("Value", "Wert");
                    $this->DisableAction("Value");
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
                    if ( $this->ReadPropertyInteger("DataType") === 1 )
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
                $Data = json_decode($Data->Buffer);
                
                $DataType = (string)$this->ReadPropertyInteger("DataType");
                foreach ($Data->$DataType as $Key => $Value) 
                {
                    if ( $Key == $this->ReadPropertyInteger("Address") )
                    {
                        switch ($this->ReadPropertyInteger("DataType")) 
                        {
                            case 3:                                
                                $Value = PhpType::bytes2signedInt($Value);
                            break; 
                            case 4:                                
                                $Value = PhpType::bytes2signedInt($Value);
                            break; 
                        }   
                        if ( GetValue($this->GetIDForIdent("Value")) <> $Value )
                        {
                            SetValue($this->GetIDForIdent("Value"), $Value);
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
            if (@$this->ReadPropertyInteger("DataType") === 1)
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
            if (@$this->ReadPropertyInteger("DataType") === 3)
            {       
                $resultat = $this->SendDataToParent(json_encode(Array("DataID" => "{A3419A88-C83B-49D7-8706-D3AFD596DFBB}", "FC" => "6", "Address" => $this->ReadPropertyInteger("Address"), "Data" => $Value)));  
                return $resultat;
            }
            else 
            {
                trigger_error("Invalid DataType!", E_USER_WARNING);
            }
        }       
        
    }