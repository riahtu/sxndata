<?php
//======================================================
// sxn_sidFunctions.php
//======================================================

//======================================================
class controlSaxenHeater {
// CSH
// Control of Heating system in Kil, Runnevål
//======================================================
    function doIt() {
    lib_log("CSH","----- Start -----\n");
    
    // Get current time and date
    $dtz = new DateTimeZone("Europe/Stockholm"); //Your timezone
    $now = new DateTime(date("Y-m-d H:i:s"), $dtz);
    $snow =  $now->format("Y-m-d H:i:s");    
    //================================
    // Configuration
    //================================
    $waterIn_sid     = 3;// 3
    $waterOut_sid    = 1;// 1
    $smokeTemp_sid   = 4;// 4
    $outdoorTemp_sid = 9;// Garage indoor
    $indoorTemp_sid  = 2;// 2
        

        
    // Memories
    //-------------------------------
    $labelTargetTemperature   = "CSH_targetTemperature";
    $labelLatestOrderTime     = "CSH_latestOrderTime";

    //======================================================== 
    $targetTemp = (int)lib_recall($labelTargetTemperature); 
    $lowWaterOut   = $targetTemp - 1.0; //26.0;
    $highWaterOut  = $targetTemp + 1.0; //28.0;
    $inertiaTime   = 360; // sec
        
        
    // Recall time and date for latest order
    $prev = lib_recall($labelLatestOrderTime); 
    if($prev == 'void')
    {
        $prev = $snow;
        lib_remember($labelLatestOrderTime,$snow); 
    }
    $diff = strtotime($snow) - strtotime($prev);
    lib_log("CSH","==> $snow [$prev] $diff($inertiaTime)\n");  
        
    //========================================================     
    $waterIn    = lib_getLatestValue($waterIn_sid);
    if($waterIn == SXN_NO_VALUE)
    {
        lib_log("CSH","No value for WaterIn\n");
        return;
    }
    $waterOut    = lib_getLatestValue($waterOut_sid);
    if($waterOut == SXN_NO_VALUE)
    {
        lib_log("CSH","No value for WaterOut\n");
        return;
    }
    if($waterOut < ($targetTemp -7.0))
    {
        lib_log("CSH","Un-realistic value for WaterOut\n");
        return;
    }
    $smokeTemp   = lib_getLatestValue($smokeTemp_sid);
    if($smokeTemp == SXN_NO_VALUE)
    {
        lib_log("CSH","No value for SmokeTemp\n");
        return;
    }
    $outdoorTemp = lib_getLatestValue($outdoorTemp_sid);
    if($outdoorTemp == SXN_NO_VALUE) 
    {
        lib_log("CSH","No value for OutdoorTemp\n"); 
    }    
    $indoorTemp  = lib_getLatestValue($indoorTemp_sid);
    if($outdoorTemp == SXN_NO_VALUE) 
    {
        lib_log("CSH","No value for IndoorTemp\n"); 
    }    
    //======================================================== 
    $energy =  100*($waterOut - $waterIn);
              
    $logmsg = "Indoor      = ".$indoorTemp."\n";    lib_log("CSH",$logmsg);
    $logmsg = "WaterIn     = ".$waterIn."\n";       lib_log("CSH",$logmsg);
    $logmsg = "WaterOut    = ".$waterOut."\n";      lib_log("CSH",$logmsg);
    $logmsg = "TargetTemp  = ".$targetTemp."\n";    lib_log("CSH",$logmsg);
    $logmsg = "lowTemp     = ".$lowWaterOut."\n";   lib_log("CSH",$logmsg);
    $logmsg = "highTemp    = ".$highWaterOut."\n";  lib_log("CSH",$logmsg);
    $logmsg = "OutdoorTemp = ".$outdoorTemp."\n";   lib_log("CSH",$logmsg);
    $logmsg = "SmokeTemp   = ".$smokeTemp."\n";     lib_log("CSH",$logmsg);
    $logmsg = "Energy      = ".$energy."\n";        lib_log("CSH",$logmsg);
    $freqBurnerOn = lib_recall("CSH_freqBurnerOn");
    $freqBurnerOff = lib_recall("CSH_freqBurnerOff");
    $logmsg = "Freq On     = ".$freqBurnerOn."\n";  lib_log("CSH",$logmsg);
    $logmsg = "Freq Off    = ".$freqBurnerOff."\n"; lib_log("CSH",$logmsg);
        
    
    $smokeDir = lib_recall("CSH_smokeDir");
    if($smokeDir == 'void')$smokeDir = 1;
    if($smokeDir == 1)lib_log("CSH","Burner ON\n");
    if($smokeDir == 2)lib_log("CSH","Burner OFF\n");
    lib_log("CSH","Action:");
        
if($smokeTemp > 42.0 && $smokeDir == 2)
{
    lib_remember("CSH_smokeDir",1);
    $timeBurnerOn = lib_recall("CSH_timeBurnerOn");
    $freqBurnerOn = (int)(strtotime($snow) - strtotime($timeBurnerOn))/60.0; // minutes
    lib_remember("CSH_timeBurnerOn",$snow);
    $logmsg = "Burner ON ".$freqBurnerOn."\n";  lib_log("CSH",$logmsg);
    lib_remember("CSH_freqBurnerOn",$freqBurnerOn);
}
if($smokeTemp < 42.0 && $smokeDir == 1)
{
    lib_remember("CSH_smokeDir",2);
    $timeBurnerOff = lib_recall("CSH_timeBurnerOff");
    $freqBurnerOff = (int)(strtotime($snow) - strtotime($timeBurnerOff))/60.0; // minutes
    lib_remember("CSH_timeBurnerOff",$snow);
    $logmsg = "Burner OFF ".$freqBurnerOff."\n"; lib_log("CSH",$logmsg);
    lib_remember("CSH_freqBurnerOff",$freqBurnerOff);
}
        
    if($diff > $inertiaTime) // 3 minutes for order to effect the temperature  
    {
     
    //echo("Algo: $delta<br>");
      lib_log("CSH","Wake Up ");
      if($smokeTemp > 26.0 ) // Only control if Heater is ON
      {
        if($waterOut < $lowWaterOut) // Increase Heat
        {
              lib_log("CSH","C_UP");
              $steps = ($highWaterOut + $lowWaterOut)/2.0 - $waterOut;
              $steps = round($steps*4);
              if($steps < 1 || $steps > 50)
              {
                $steps = 1;
                $logmsg = "Error: Steps out of range ".$steps."\n";     lib_log("CSH",$logmsg);
              }
              lib_log("CSH"," + ");
              $order = "NBC_STEPPER_CTRL 1 ".$steps." 20";
              lib_log("CSH",$order);
              insertOrder($waterOut_sid,$order);
              lib_remember($labelLatestOrderTime,$snow); 
        }
        else if($waterOut > $highWaterOut) // Decrease Heat
        {
              lib_log("CSH","C_DOWN");
              $steps = $waterOut - ($highWaterOut + $lowWaterOut)/2.0;
              $steps = round($steps*4);
              if($steps < 1 || $steps > 50)
              {
                $steps = 1;
                $logmsg = "Error: Steps out of range ".$steps."\n";     lib_log("CSH",$logmsg);
              }
              lib_log("CSH"," - ");
              $order = "NBC_STEPPER_CTRL 2 ".$steps." 20";
              lib_log("CSH",$order);
              insertOrder($waterOut_sid,$order); 
              lib_remember($labelLatestOrderTime,$snow); 
        }
        else
        {
            lib_log("CSH"," Temperature within target ");
        }
          
         
      }
      else
      {
           if($smokeTemp < 25.0)lib_log("CSH","Heater is off ");
           if($waterOut < $waterIn)lib_log("CSH","No heating is needed "); 
      }
    }
    else
    {
        lib_log("CSH","Sleeping");
    }
    lib_log("CSH","\n");
    lib_log("CSH","-----End-----\n");     
    } // doIt
} // Class

//======================================================
class autoMowerMap 
{
//======================================================
    function doIt() 
    {
        
        $sid_lat = 801; 
        $sid_lon = 802; 
        $sid_battery_status = 803; 
        $latitude    = lib_getLatestValue($sid_lat);
        if($latitude == SXN_NO_VALUE)
        {
            lib_log("CSH","No value for latitud t\n");
            lib_log("CSH","-----End-----\n"); 
        }
        $longitude    = lib_getLatestValue($sid_lon);
        if($longitude == SXN_NO_VALUE)
        {
            lib_log("CSH","No value for longitude t\n");
            lib_log("CSH","-----End-----\n"); 
        }
        $battery_status    = lib_getLatestValue($sid_battery_status);
        if($battery_status == SXN_NO_VALUE)
        {
            lib_log("CSH","No value for battery status t\n");
            lib_log("CSH","-----End-----\n"); 
        }
        echo("Mapping ");
    }
}
//======================================================
class template {
// 
// Name your class!
//======================================================
    function doIt() {
        
        // Write your code here
        
        }
}
