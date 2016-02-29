<?php
    class ModBusMaster extends IPSModule
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
            
            $this->RegisterPropertyBoolean("Active", false);
            $this->RegisterPropertyString("IPAddress", "192.168.1.1");
            $this->RegisterPropertyInteger("GatewayMode", 0);
            $this->RegisterPropertyInteger("DeviceID", 1);
            $this->RegisterPropertyInteger("Poller", 200);
            $this->RegisterTimer("Poller", 0, "MBMaster_Update(\$_IPS['TARGET']);");           
            $this->RegisterPropertyInteger("CoilsReference", 512);
            $this->RegisterPropertyInteger("CoilsQuantity", 0);              
            $this->RegisterPropertyInteger("DiscretesReference", 0);
            $this->RegisterPropertyInteger("DiscretesQuantity", 0);    
            $this->RegisterPropertyInteger("RegistersReference", 512);
            $this->RegisterPropertyInteger("RegistersQuantity", 0);    
            $this->RegisterPropertyInteger("InputRegistersReference", 0);
            $this->RegisterPropertyInteger("InputRegistersQuantity", 0);           
        }

        /**
         * ApplyChanges
         */
        public function ApplyChanges()
        {
            //Never delete this line!
            parent::ApplyChanges();     
         
            if ( $this->ReadPropertyBoolean("Active") ) 
            {                     
                if ( @Sys_Ping($this->ReadPropertyString("IPAddress"), 1000) )
                {              
                    $this->SetStatus(102); 
                    if ($this->ReadPropertyInteger("Poller") < 500)
                    {
                        $this->SetTimerInterval("Poller", 500);
                    } else 
                    {
                        $this->SetTimerInterval("Poller", $this->ReadPropertyInteger("Poller"));
                    }
                }
                else 
                {
                    $this->SetStatus(201); 
                    $this->SetTimerInterval("Poller", 0);
                    echo "Invalid IP-Address";
                }
            } 
            else 
            { 
                $this->SetStatus(104); 
                $this->SetTimerInterval("Poller", 0);
            }             
        }      
        
        /**
         * ForwardData
         * @param sring $JSONString
         * @return boolean
         */
        public function ForwardData($JSONString) 
        {
            // Empfangene Daten von der Device Instanz
            $data = json_decode($JSONString);
            if ( ($data->DataID === "{A3419A88-C83B-49D7-8706-D3AFD596DFBB}") and ($this->ReadPropertyBoolean("Active")) )
            {
                if ( $data->FC == 5 )
                {
                    $this->WriteSingleCoil($this->ReadPropertyInteger("DeviceID"), $data->Address, $data->Data);
                }
                if ( $data->FC == 6 )
                {
                    $this->WriteSingleRegister($this->ReadPropertyInteger("DeviceID"), $data->Address, $data->Data);
                }
            }                   
            return true;
        }
        
        /**
         * MBMaster_Update();
         */
        public function Update()
        {                
            $this->Log('Update ...');
            $tstart = microtime(true);            
            include_once(__DIR__ . "/lib/ModbusMaster.php");
            $URL = "http://" . $this->ReadPropertyString("IPAddress");
            if ( !Sys_Ping($this->ReadPropertyString("IPAddress"), 1000) )
            {
                $this->SetStatus(201);                 
                trigger_error("Invalid IP-Address", E_USER_ERROR);
                exit;
            }                        
            if ( $this->ReadPropertyInteger("GatewayMode") === 0 )
            {
                $modbus = new ModbusMasterTcp($this->ReadPropertyString("IPAddress"));
            }
            else
            {
                $modbus = new ModbusMasterUdp($this->ReadPropertyString("IPAddress"));
            } 
            $count = 1; 
            if ( $this->ReadPropertyInteger("Poller") < 1000)
            {                
                $count = 1000 / $this->ReadPropertyInteger("Poller");
            }
            for ($index = 1; $index < $count; $index++) 
            { 
                $this->Log('Update (' . $count . ') | ' . number_format(((microtime(true)-$tstart)*1000),2) . ' ms');
                $tstartfor = microtime(true);           
                $data = array();
                // FC 1 Rücklesen mehrerer digitaler Ausgänge 
                if ( $this->ReadPropertyInteger("CoilsQuantity") > 0)            
                {    
                    try 
                    {   
                        if (IPS_SemaphoreEnter("ModbusMaster", 1000))
                        {
                            $this->Log('Update (FC1) | ' . number_format(((microtime(true)-$tstart)*1000),2) . ' ms');
                            $recData = $modbus->readCoils($this->ReadPropertyInteger("DeviceID"), $this->ReadPropertyInteger("CoilsReference"), $this->ReadPropertyInteger("CoilsQuantity")); 
                            IPS_SemaphoreLeave("ModbusMaster");
                        }
                    }
                    catch (Exception $e) 
                    {       
                        $this->SetStatus(200); 
                        trigger_error("ModbusMaster: " . $e->getMessage() . "!", E_USER_ERROR);
                        exit;
                    }                    
                    $Address = $this->ReadPropertyInteger("CoilsReference");    
                    foreach ($recData as $Value) 
                    {                           
                        $data["FC1"][$Address] = $Value;
                        $Address++;         
                    }                
                }            
                // FC 2 Lesen mehrerer digitaler Eingänge 
                if ( $this->ReadPropertyInteger("DiscretesQuantity") > 0)            
                {    
                    try 
                    {
                        if (IPS_SemaphoreEnter("ModbusMaster", 1000))
                        {
                            $this->Log('Update (FC2) | ' . number_format(((microtime(true)-$tstart)*1000),2) . ' ms');
                            $recData = $modbus->readInputDiscretes($this->ReadPropertyInteger("DeviceID"), $this->ReadPropertyInteger("DiscretesReference"), $this->ReadPropertyInteger("DiscretesQuantity")); 
                            IPS_SemaphoreLeave("ModbusMaster");
                        }
                    }
                    catch (Exception $e) 
                    {       
                        $this->SetStatus(200); 
                        trigger_error("ModbusMaster: " . $e->getMessage() . "!", E_USER_ERROR);
                        exit;
                    }
                    $Address = $this->ReadPropertyInteger("DiscretesReference");
                    foreach ($recData as $Value) 
                    {                           
                        $data["FC2"][$Address] = $Value;
                        $Address++;         
                    }                
                }  
                // FC 3 Lesen mehrerer analoger Eingänge(und Ausgänge) 
                if ( $this->ReadPropertyInteger("RegistersQuantity") > 0)
                {  
                    try 
                    {
                        if (IPS_SemaphoreEnter("ModbusMaster", 1000))
                        {
                            $this->Log('Update (FC3) | ' . number_format(((microtime(true)-$tstart)*1000),2) . ' ms');
                            $recData = $modbus->readMultipleRegisters($this->ReadPropertyInteger("DeviceID"), $this->ReadPropertyInteger("RegistersReference"), $this->ReadPropertyInteger("RegistersQuantity")); 
                            IPS_SemaphoreLeave("ModbusMaster");
                        }
                    }
                    catch (Exception $e) 
                    {       
                        $this->SetStatus(200); 
                        trigger_error("ModbusMaster: " . $e->getMessage() . "!", E_USER_ERROR);
                        exit;
                    }                    
                    $Address = $this->ReadPropertyInteger("RegistersReference");
                    $Values = array_chunk($recData, count($recData) / $this->ReadPropertyInteger("RegistersQuantity") ); 
                    foreach ($Values as $Value) 
                    {                      
                        $data["FC3"][$Address] = $Value;
                        $Address++;
                    }
                }
                // FC 4 Lesen mehrerer analoger Eingänge(und Ausgänge) 
                if ( $this->ReadPropertyInteger("InputRegistersQuantity") > 0)
                {  
                    try 
                    {
                        if (IPS_SemaphoreEnter("ModbusMaster", 1000))
                        {
                            $this->Log('Update (FC4) | ' . number_format(((microtime(true)-$tstart)*1000),2) . ' ms');
                            $recData = $modbus->readMultipleInputRegisters($this->ReadPropertyInteger("DeviceID"), $this->ReadPropertyInteger("InputRegistersReference"), $this->ReadPropertyInteger("InputRegistersQuantity")); 
                            IPS_SemaphoreLeave("ModbusMaster");
                        } 
                    }
                    catch (Exception $e) 
                    {       
                        $this->SetStatus(200); 
                        trigger_error("ModbusMaster: " . $e->getMessage() . "!", E_USER_ERROR);
                        exit;
                    }
                    $Address = $this->ReadPropertyInteger("InputRegistersReference");
                    $Values = array_chunk($recData, count($recData) / $this->ReadPropertyInteger("InputRegistersQuantity") ); 
                    foreach ($Values as $Value) 
                    {                      
                        $data["FC4"][$Address] = $Value;
                        $Address++;
                    }
                } 
                $this->SendDataToChildren(json_encode(Array("DataID" => "{449015FB-6717-4BB6-9F95-F69945CE1272}", "Buffer" => json_encode($data))));                       
                $this->SetStatus(102); 
                $this->Log(number_format(((microtime(true)-$tstart)*1000),2) . ' ms');
                if ( $this->ReadPropertyInteger("Poller") < 1000)
                {
                    IPS_Sleep($this->ReadPropertyInteger("Poller") - ((microtime(true)-$tstartfor) * 1000));           
                }    
                $this->Log(number_format(((microtime(true)-$tstart)*1000),2) . ' ms');
            }
            $this->Log('Update! | ' . number_format(((microtime(true)-$tstart)*1000),2) . ' ms');
        }
        

        /** *** WORKAROUND ***
         * SendDataToChildren
         * @param string $JSONString
         */
        protected function SendDataToChildren($JSONString) 
        {
            //parent::SendDataToChildren($Data);
            include_once(__DIR__ . "/../ModbusMasterDevice/module.php");
            $ModuleID_r = IPS_GetInstanceListByModuleID("{3BD8FD26-AFAC-49D4-A9F0-15DE90A41D26}");
            foreach ($ModuleID_r as $value) 
            {
                $Device = new ModBusMasterDevice($value);
                $Device->ReceiveData($JSONString);                
            }
        }

        /**
         * WriteSingleCoil
         * @param integer $UnitId
         * @param integer $Reference
         * @param boolean $Data
         */
        protected function WriteSingleCoil($UnitId, $Reference, $Data)
        {
            include_once(__DIR__ . "/lib/ModbusMaster.php");
            $URL = "http://" . $this->ReadPropertyString("IPAddress");
            //$headers = @get_headers($URL);
            if ( !Sys_Ping($this->ReadPropertyString("IPAddress"), 1000) )
            {
                $this->SetStatus(201); 
                trigger_error("Invalid IP-Address", E_USER_ERROR);
                exit;
            }
            $this->SetStatus(102); 
            if ( $this->ReadPropertyInteger("GatewayMode") === 0 )
            {
                $modbus = new ModbusMasterTcp($this->ReadPropertyString("IPAddress"));
            }
            else
            {
                $modbus = new ModbusMasterUdp($this->ReadPropertyString("IPAddress"));
            }
            //FC 5
            try 
            {
                if (IPS_SemaphoreEnter("ModbusMaster", 1000))
                {
                    $modbus->writeSingleCoil($UnitId, $Reference, array($Data));
                    IPS_SemaphoreLeave("ModbusMaster");
                }
            }
            catch (Exception $e) 
            {       
                $this->SetStatus(200); 
                trigger_error("ModbusMaster: " . $e->getMessage() . "!", E_USER_ERROR);
                exit;
            }
        }

        /**
         * WriteSingleRegister
         * @param integer $UnitId
         * @param integer $Reference
         * @param integer $Data
         */
        protected function WriteSingleRegister($UnitId, $Reference, $Data)
        {
            include_once(__DIR__ . "/lib/ModbusMaster.php");
            $URL = "http://" . $this->ReadPropertyString("IPAddress");
            //$headers = @get_headers($URL);
            if ( !Sys_Ping($this->ReadPropertyString("IPAddress"), 1000) )
            {
                $this->SetStatus(201); 
                trigger_error("Invalid IP-Address", E_USER_ERROR);
                exit;
            }
            $this->SetStatus(102); 
            if ( $this->ReadPropertyInteger("GatewayMode") === 0 )
            {
                $modbus = new ModbusMasterTcp($this->ReadPropertyString("IPAddress"));
            }
            else
            {
                $modbus = new ModbusMasterUdp($this->ReadPropertyString("IPAddress"));
            }
            //FC 6
            try 
            {
                if (IPS_SemaphoreEnter("ModbusMaster", 1000))
                {
                    $modbus->writeSingleRegister($UnitId, $Reference, array($Data), array("INT"));
                    IPS_SemaphoreLeave("ModbusMaster");
                }
            }
            catch (Exception $e) 
            {       
                $this->SetStatus(200); 
                trigger_error("ModbusMaster: " . $e->getMessage() . "!", E_USER_ERROR);
                exit;
            }
        }                
    }