<?php
	$lang = "en"; 	require_once("LanguageEN.php");	$LeagueName = Null;	session_start();	mb_internal_encoding("UTF-8");	$PerformanceMonitorStart = microtime(true); 
	require_once("STHSSetting.php");
	require_once("WebClientAPI.php");

    require_once("helperTool.php");
	
	$exempt = array();  // exempt is an array of api names.  if you do not need the html or layout api then add as an array item   example:  $exempt = array("html","layout");
	load_apis($exempt); // Call the required APIs
	
	// Make a connection variable to pass to API
	$db = api_sqlite_connect($DatabaseFile);


	if ($db){
		
		$Query = "Select ShowWebClientInDymanicWebsite FROM LeagueOutputOption";
		$LeagueOutputOption = $db->querySingle($Query,true);
		
		$Query = "Select BlockAutoProLineFunctionForGM,BlockAutoFarmLineFunctionForGM FROM LeagueWebClient";
		$LeagueWebClient = $db->querySingle($Query,true);

		// Look for a team ID in the URL, if non exists use 0
		$t = (isset($_REQUEST["TeamID"])) ? filter_var($_REQUEST["TeamID"], FILTER_SANITIZE_NUMBER_INT): 0;
		$l = (isset($_REQUEST["League"])) ? filter_var($_REQUEST["League"], FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW || FILTER_FLAG_STRIP_HIGH) : false;
		If (strtolower($l) <> "farm"){$l = "Pro";}else{$l = "Farm";}
		$row = array();
		if($t > 0 AND $t <= 100){
			$rs = api_dbresult_teamsbyname($db,"Pro",$t);
			$row = $rs->fetchArray();
		}
		If ($l == "Pro" AND $LeagueWebClient['BlockAutoProLineFunctionForGM'] == "True"){echo "<style>#autolines {display:none};</style>";}
		If ($l == "Farm" AND $LeagueWebClient['BlockAutoFarmLineFunctionForGM'] == "True"){echo "<style>#autolines {display:none};</style>";}
		
        
        // LHSQC
        $WebClientHeadCode = "
        <link href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css\" rel=\"stylesheet\">  
                                             
        <script src=\"https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js\"></script>
        <script src=\"https://code.jquery.com/ui/1.14.1/jquery-ui.min.js\"></script>
        <script src=\"https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js\"></script>
       
        <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\" integrity=\"sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH\" crossorigin=\"anonymous\">
       
        <link href=\"STHSMain-CSSOverwrite.css\" rel=\"stylesheet\" type=\"text/css\" /> 
        <script src=\"js/lhsqc_new.js\"    type=\"text/javascript\"></script>
        <link href=\"https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;700&display=swap\" rel=\"stylesheet\">
        <link href=\"css/nhlColors.css\" rel=\"stylesheet\" type=\"text/css\" /> ";
        //<script src=\"js/db2json.js\"    type=\"text/javascript\"></script>";
        

        //<link rel=\"stylesheet\" href=\"https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css\">";
        //<script src=\"LHSQC.js\"    type=\"text/javascript\"></script>";  


		// Make a default header 
		api_layout_header("lineeditor",$db,$t,$l,$WebClientHeadCode);
        

		If ($CookieTeamNumber == 102){$DoNotRequiredLoginDynamicWebsite = TRUE;} // Commish is allow to edit any Teams so we are using the code from the 'Do Not Required Login Dynamic Website' to achieve this goal.
		
		if(($CookieTeamNumber == $t OR $DoNotRequiredLoginDynamicWebsite == TRUE) AND $t > 0 AND $t <= 100){
			// Display the line editor page using API.
			// use 4 paramaters Database, TeamID, $league("Pro","Farm"), showH1Tag (DEFAULT true/false)   
			if($t > 0){   // Check to see if there is a team selected.
                
                $league=$l;
                $useServerURIInTabLink=false;
                // $db = sqlite DB
                // $teamid is a teamid to use that teams roster.
                // $league is "Pro" or "Farm" based on selection.         
            
                $teamid = $t;
                
                $Query = "SELECT * FROM TeamProInfo WHERE Number = " . $teamid;
		        $TeamProInfo = $db->querySingle($Query,true);
                $Theme = $TeamProInfo['TeamThemeID'];


                $Query = "SELECT * FROM PlayerInfo WHERE Team = " . $teamid; 
                $results = $db->query($Query); 
                $TeamPlayerInfo = []; 
                while ($row = $results->fetchArray(SQLITE3_ASSOC)) { $TeamPlayerInfo[] = $row; }
        



                // Set the status value if the league is Pro or Farm
                $status = ($league == "Pro") ? 3: 1;
                // Select all the players and goalies if they are dressed.
                $sql = "SELECT Number, Name FROM PlayerInfo WHERE Team = " . $teamid . " AND Status1 = " . $status . " ";
                $sql .= "UNION ";
                $sql .= "SELECT Number, Name FROM GoalerInfo WHERE Team = " . $teamid . " AND Status1 = " . $status . " ";
    
                // Get the recordset of all the players
                $oRS = $db->query($sql);
                // Make an array of available players to use.
                // This makes comparing from a roster change easier.
                // i.e. Database could show a player in a position in the lines table, but if that
                // player was scratched, or moved between farm and pro, there has to be a way to
                // show he isn't there and show blank on the line. 
                $availableplayers = array();
                while($row = $oRS->fetchArray()){
                    $availableplayers[api_MakeCSSClass($row["Name"])]["id"] = $row["Number"];
                    $availableplayers[api_MakeCSSClass($row["Name"])]["Name"] = $row["Name"];
                }
                log2console($availableplayers);
               
                // Check to see if Custom OT lines are turned on 
                $sql = "SELECT " . $league . "CustomOTLines AS CustomLines FROM LeagueGeneral;";
                $oRS = $db->query($sql);
                $row = $oRS->fetchArray();
                $customOTlines = ($row["CustomLines"] == "True") ? true: false;
                $cpfieldsOTLines = ($customOTlines) ? 'true': 'false';
    
                // get the fields needed for the ChangePlayer function onClick
                $dbfields = api_dbresult_line_editor_fields($db);
                $cpfields = "";
                foreach($dbfields AS $f){$cpfields .= strtolower($f) .",";}
                $cpfields .= $cpfieldsOTLines;
                //$cpfields = rtrim($cpfields,",");

                $bannertext = "";
                
                // If the updatelines submit button is clicked 
                if(isset($_POST["sbtUpdateLines"])){
                    $fminfo = "";
                    $dbfields = api_get_fields($db,$customOTlines,$league);
                    $fmfields = array_merge($_POST["txtLine"],$_POST["txtStrategies"]);
        
                    $sql = "SELECT " . implode(" || ',' || ", $dbfields) . " AS LineValues FROM Team". $league ."Lines WHERE TeamNumber = " . $teamid . " AND Day = 1;";
                    $oRS = $db->query($sql);
                    $row = $oRS->fetchArray();
                    $dbinfo = $row["LineValues"];
                    foreach($dbfields AS $f){
                        if (array_key_exists($f,$fmfields)){$fminfo .= $fmfields[$f] . ",";}else{$fminfo .= "0,";}
                    }
                    $fminfo = rtrim($fminfo,",");
                
                    if(trim($fminfo) != trim($dbinfo)){
                        $arrDB = explode(",",$dbinfo);
                        $arrFM = explode(",",$fminfo);
                        $dbfields = array_values($dbfields);
                        
                        // Need 2 running query strings: one for the regular lines table
                        // And one for the numberonly table.
                        // For now this will update all 10 game slots for lines.
                        $sql   = "UPDATE Team". $league ."Lines SET ";
                        $sqlno = "UPDATE Team". $league ."LinesNumberOnly SET ";
                        
                        foreach($dbfields AS $i=>$f){
                            if($arrDB[$i] != $arrFM[$i]){
                                if(is_numeric($arrFM[$i])){
                                    $val    = api_sqlite_escape($arrFM[$i]);
                                    $valno  = api_sqlite_escape($arrFM[$i]);
                                }else{
                                    $val    = "'" . api_sqlite_escape($arrFM[$i]) . "'";
                                    if ($val == "''" || !isset($availableplayers[api_MakeCSSClass($arrFM[$i])])){$valno = 0;}else{$valno  = $availableplayers[api_MakeCSSClass($arrFM[$i])]["id"];}
                                }
                                $sql   .= $f . " = " . $val . ", ";
                                $sqlno .= $f . " = " . $valno . ", ";
                            }
                        }
          
                        
                        $sql = rtrim($sql,", ");
                        $sqlno .= " WebClientModify = 'True' ";
                        $sqlno .= ", WebClientIP = '" . $_SERVER['REMOTE_ADDR'] . "' ";
                        $sql .= " WHERE TeamNumber = " . $teamid . ";";
                        $sqlno .= " WHERE TeamNumber = " . $teamid . ";";
                        $db->busyTimeout(5000);
                        $db->exec("pragma journal_mode=memory;");
                        $db->exec($sql);
                        $db->exec($sqlno);	
                        
                        $row = ($teamid > 0) ? api_dbresult_teamname($db,$teamid,$league) : array();
                        $teamname = (!empty($row)) ? $row["FullTeamName"] . " - " : "";
                        $TransactionSQL = "INSERT Into LeagueLog (Number, Text, DateTime, TransactionType) VALUES ('" . rand(90000,99999) . "','Save Lines for " . $teamname . "','" . gmdate('Y-m-d H:i:s') . "','8')";
                        $db->exec($TransactionSQL);
                        
                        $c = "Lines have been saved.";
                    }else{
                        $bannertext = "No changes have been made.";
                    }
                }// end isset $_POST[sbtUpdateLines]?>












<header class="teamColorPale<?= $Theme ?>">
<?php include "Menu.php";?>


<div id="lineeditor">
    <div class="container pagewrapper pagewrapperlineeditor ">
      
             <div class="lineSave pb-3 "> 
                <div class="row  darkText justify-content-center pt-1 m-0 px-0">

                    <div class="col-3 ">
                        <button id="autolines" onClick="javascript:auto_lines('<?= $league ?>',<?= $cpfields ?>);" type="button" name="btnAutoLines" class="btn btn-info  m-1 shadow "> Auto </button>
                        <button id="linesubmit" type="submit" name="sbtUpdateLines" form="submissionform" class="btn btn-warning  m-1 shadow "> Save</button>
                    </div>
                    <div class="col "> 
                        <div id="errors"></div>
                        <div id="confirmDiv" class="confirm banner"><?php if($bannertext) echo $bannertext; else  echo ""; ?>    </div>
                        <h4 class="darkText"><?= api_make_nextgame($nextgames[1],$league);?></h4>
                    </div> 

                    <div class="col-3"> 
                        <button type="button" class="btn btn-warning btn-custom openList btn-lg shadow " > <img src="images/roster.png" alt="Button 2" ><br>Roster </button>
                    </div> 
                </div>
            </div>
           
       
 

        <form id="submissionform" class="STHSWebClient_Form " name="frmEditLines" method="POST" onload="checkCompleteLines();">

            
                   
           

                    <?php  
                        $nextgames = api_get_nextgames($db,$t); // Create a next 10 games array to see the games both Pro and Farm will play.
                                                
                        if($league == "Pro"){ $status1 = 3; $isPro = true;  } // Set Pro variables  
                        else                { $status1 = 1; $isPro = false; } // Set Farm variables
                        
                        // Select all the lines and players in them for the next game.
                        $sql = "SELECT l.* FROM Team". $league ."Lines AS l LEFT JOIN Team". $league ."Info AS t ON t.Number = l.TeamNumber ";
                        $sql .= "WHERE t.Number = '" . $teamid . "' AND Day = 1 ";
                        $oRS = $db->query($sql);
                        $row = $oRS->fetchArray();

                        //log2console($row);        
                        // Fill in arrays needed. // tabs = line pages, 	blocks =  section per page, positions = different position combination for the blocks,	strategy = strategy slider info.  
                        $tabs = api_get_line_arrays("tabs");
                        $blocks = api_get_line_arrays("blocks");
                        $positions = api_get_line_arrays("positions");
                        $strategy = api_get_line_arrays("strategy");
                    ?>
                        
                        



                    <div id="tabs" class="linetabs">
                        <div class="menuPin">
                            <div class="tabsMenu ">
                                <ul class="nav flex-column  " role="tablist">
                                    <?php  
                                    // loop through the tab names creating clickable tabs.
                                    $count = 0; 
                                    $tablink = ($useServerURIInTabLink) ? $_SERVER["REQUEST_URI"] . "#tabs-" : "#tabs-";
                                    foreach($tabs AS $i=>$t){
                                        $displaytab = false;
                                        if($i != "OT")                       $displaytab = true;
                                        elseif($i == "OT" && $customOTlines) $displaytab = true;
                                        else                                 $displaytab = false;
                                        
                                        if($displaytab){

                                            $activeClass = $count ? '' : 'active';
                                        
                                            
                                            ?> 
                                            <li class=" nav-item <?= $activeClass ?>" >
                                                <button class="nav-link <?= $activeClass ?>  id="<?= $t?>-tab" data-bs-toggle="tab" data-bs-target="<?= $tablink . ++$count?>" type="button" role="tab"  tabindex="-1"><?= $t?></button> 
                                            </li><?php


                                            
                                        }
                                    }
                                    $count = 0;
                                    ?>	
                                </ul>
                            </div>
                        </div>

                        <div id="tabs" class="teamColorSecondary<?= $Theme ?> tab-content ">
                                <?php // Loop through the tabs info making the lines pages.
                                foreach($tabs AS $i=>$t) {
                                    $displaytab = false;
                                    if($i != "OT")                       $displaytab = true;
                                    elseif($i == "OT" && $customOTlines) $displaytab = true; 
                                    else                                 $displaytab = false;
                                    
                                    if($displaytab) { ?>
                                        <div id="tabs-<?= ++$count ?>" class="tabcontainer  tab-pane <?php echo ($count == 1) ? 'active show' : ''; ?>  teamColorSecondary<?= $Theme ?> ">
                                        <?php 
//  TABS LINES 
                                        if($i == "Forward" || $i == "Defense" || $i == "PP" || $i == "PK4" || $i == "4VS4" || $i == "PK3"){	
                                            // Each of the if'ed statements above have the same kind of info. 
                                            // display the blocks based on which tabbed page you are on.
                                           
                                            $bcount = 0;
                                            foreach($blocks[$i] AS $bid=>$block){?>
                                                    <div class="  card p-1 id<?= api_MakeCSSClass($i)?> id<?= api_MakeCSSClass($bid)?> teamColorSecondary<?= $Theme ?> ">
                                                        
                                                        <div class=" d-flex justify-content-between align-items-center" >
                                                            <div class=" fs-10 text-start px-2"><?= $block ?> </div>
                                                            <div class="text-end m-0"> 
                                                                <div class="button-container">
                                                                    <div class="btn-group">   
                                                                        <button type="button" class="btn btn-warning btn-custom"  class="toggle-active"  id="toggleButton<?= api_MakeCSSClass($bid)?> " onclick="toggler(event,`sliders<?= api_MakeCSSClass($bid)?>`)" > 
                                                                            <img src="images/strategy.png" alt="Button 1" ></button>
                                                                        <button type="button" class="btn btn-warning btn-custom " class="toggle-active"  id="toggleButton<?= api_MakeCSSClass($bid)?> " onclick="toggler(event, 'persona<?= api_MakeCSSClass($bid)?>')" >
                                                                            <i class="fa-solid fa-user"></i></button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="blockcontainer">
                                                            <div class="positionwrapper">
                                                                <?php 	
                                                                // Depending on which page you are on sets up how many blocks are needed.
                                                                // If its anything but 5vs5
                                                                if($i == "PP" || $i == "PK4" || $i == "4VS4" || $i == "PK3"){
                                                                     if($bid == strtolower($i) . "line1" || $bid == strtolower($i) . "line2"){
                                                                        if($i == "PP"){
                                                                            $posit = $positions["Forward"];
                                                                        }elseif($i == "PK3"){
                                                                            $posit = $positions["Forward3"];
                                                                        }else{
                                                                            $posit = $positions["Forward4"];
                                                                        }
                                                                        $exp = explode("line",$bid);
                                                                        $field = "Line". $exp[1] ."". $i ."Forward";
                                                                    }else{
                                                                        $exp = explode("pair",$bid);
                                                                        $posit = $positions["Defense"];
                                                                        $field = "Line". $exp[1] ."". $i ."Defense";
                                                                    }
                                                                // else its 5vs5
                                                                }else{
                                                                    $field = "Line". ++$bcount ."5vs5" . $i;
                                                                    $posit = $positions[$i];                                                                
                                                                }?>

                                                                <div class='row pt-1 ' id='divF<?= $bcount; ?>'>
                                                                    <?php 
                                                                    foreach($posit as $pid => $pos) {
                                                                        // Set player name in each position
                                                                        $playerName = isset($availableplayers[api_MakeCSSClass($row[$field . $pid])]) ? $row[$field . $pid] : ""; //log2console($playername ."  " . $field . " -  ". $pid );
                                                                        ?>
                                                                        <div class='col-4 p-1 '>
                                                                            <div class="card rosterElm draggable p-0" draggable="true">
                                                                                <div class="card-body p-0" >
                                                                                    <input class='colorName  form-control p-0' readonly type="text" placeholder="." aria-label=".." aria-describedby="positionlabel" 
                                                                                        onclick="ChangePlayer('<?= $field . $pid; ?>','<?= $league; ?>','<?= $cpfields; ?>')" 
                                                                                        id="<?= $field . $pid; ?>" name="txtLine[<?= $field . $pid; ?>]" value="<?= $playerName ?>">
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <?php         
                                                                    }
                                                                    ?>

                                                                </div>
    
                                                            </div><!-- end positionwrapper-->
                                                            

                                                            <div class="container sliders " id='sliders<?= api_MakeCSSClass($bid)?>'>
                                                                
                                                                <?php 
                                                                foreach($strategy AS $sid=>$strat){
                                                                    $id = $field . $sid; 
                                                                    $size = 0;
                                                                    if($strat == "Strat"){$size = 1;}
                                                                    elseif($strat == "Time"){$size=3;}
                                                                    else{
                                                                        $exp = explode("-",$strat);
                                                                        if($exp[1] <= 10){$size = 2;}
                                                                        else{$size = 3;}
                                                                    } ?>
                                                                    <div class="row ">    
                                                                        <div class=" strategy strats d-flex align-items-center justify-content-center">
                                                                            <div class="stratlabel my-0 py-0 "><?= $sid?> </div>
                                                                        
                                                                            <div class="plusMinusBtn input-group mx-2 d-flex justify-content-center align-items-center" style="width: auto;">

                                                                                <span class="input-group-btn"  style="">
                                                                                    <button type="button" class="btn btn-danger btn-number form-control"  data-type="minus"   name="btnDown-<?= $id ?>" onclick="valChange('<?= $id ?>','Strat','<?=$field?>','down', '<?=$cpfields?>')">
                                                                                    <i class="fa-solid fa-minus"></i>
                                                                                    </button>
                                                                                </span>
                                                                                <input readonly type="text" id="<?= $id?>" style="width: 75px;" name="txtStrategies[<?= $id ?>]" class="form-control input-number" value="<?= $row[$id] ?>" min="1" max="100">
                                                                                <span class="input-group-btn">
                                                                                    <button type="button" class="btn btn-success btn-number m-0" name="btnUp-<?= $id ?>" data-type="plus"  onclick="valChange('<?= $id ?>','Strat','<?=$field?>','up','<?=$cpfields?>')">
                                                                                    <i class="fa-solid fa-plus"></i>
                                                                                    </button>
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div> <?php
                                                                }?>  

                                                                <div class="row">  
                                                                    <div class="  time d-flex align-items-center justify-content-center ">
                                                                        <div class="timelabel my-0 py-0">Time%</div>
                                                                    
                                                                        <?php   $id = $field . "Time";   ?>                                                                       
                                                                        <div class="input-group  plusMinusBtn mx-2 d-flex justify-content-center align-items-center" style="width: auto;">
                                                                            <span class="input-group-btn  "  style="">
                                                                                <button type="button" class="btn btn-danger btn-number "  data-type="minus" data-field="quant[2]"  name="btnDown-Time" onclick="valChange('<?= $id ?>','Time','<?=$field?>','down','<?=$cpfields?>')">
                                                                                <i class="fa-solid fa-minus"></i>
                                                                                </button>
                                                                            </span>
                                                                            <input readonly type="text" id="<?= $id?>" style="width: 75px;" name="txtStrategies[<?= $id ?>]" class="form-control input-number" value="<?= $row[$id] ?>" min="1" max="100">
                                                                            <span class="input-group-btn">
                                                                                <button type="button" class="btn btn-success btn-number  m-0" data-type="plus" data-field="quant[2]"  name="btnUp-Time"    onclick="valChange('<?= $id ?>','Time','<?=$field?>','up','<?=$cpfields?>')">
                                                                                <i class="fa-solid fa-plus"></i>
                                                                                </button>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                
                                                            </div><!-- end sliders-->


                                                            <div class="container persona" id='persona<?= api_MakeCSSClass($bid)?>'>

                                                                <div class='row pt-1 '>
                                                                    <?php 
                                                                    foreach($posit as $pid => $pos) {
                                                                        // Set player name in each position
                                                                        $playerName = isset($availableplayers[api_MakeCSSClass($row[$field . $pid])]) ? $row[$field . $pid] : ""; 
                                                                        log2console($playername ."  " . $field . " -  ". $pid );

                                                                        ?>
                                                                        <div class='col-4 p-1 '>
                                                                            <div class="card rosterElm  p-0" >
                                                                                <div class="card-body p-0" >
                                                                                   
                                                                                    <span>Overall: </span> <span id="smPov">00 </span>
                                                                                    <span>Condition: </span> <span id="smPco">00 </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <?php         
                                                                    }
                                                                    ?>

                                                                </div>
    

                                                            </div>


                                        
                                                        </div><!-- end blockcontainer-->
                                                    </div><!-- end linesection <?= api_MakeCSSClass($i)?> <?= api_MakeCSSClass($bid)?>--><?php 
                                                }

                                        }
//  TABS OTHERS                                       
                                        elseif($i == "Others"){ ?>
                                            
                                            <div class="linesection id<?= api_MakeCSSClass($i)?> goalies tab-pane"> <?php // Start with the goalies. ?>
                                                <div class="row">                                                                   
                                                    <?php 
                                                    $GoalerInGame = api_GoalerInGame($db,$league);																
                                                    foreach(array(1=>"Starting Goalie",2=>"Backup Goalie",3=>"Third Goalie") AS $gid=>$g){?>
                                                        <div class=" p-1 m-1 ">
                                                            <?php 
                                                            if ($g == "Third Goalie" AND $GoalerInGame['GoalerInGame'] == 2){echo "<h4 style=\"display:none\">". $g ."</h4>";}else{echo "<h4>". $g ."</h4>";}
                                                            $row["Goaler" . $gid] = (isset($availableplayers[api_MakeCSSClass($row["Goaler".$gid])])) ? $row["Goaler".$gid]: ""; 
                                                            ?>
                                                            <div class="blockcontainer"<?php if ($g == "Third Goalie" AND $GoalerInGame['GoalerInGame'] == 2) echo "style=\"display:none\""; ?>>
                                                                <div class="positionline">
                                                                
                                                                    <input class="textName" id="Goaler<?= $gid ?>" onclick="ChangePlayer('Goaler<?= $gid ?>', '<?= $league ?>', <?= $cpfields ?>)" readonly type="text" name="txtLine[Goaler<?= $gid ?>]" value="<?= $row["Goaler".$gid] ?>">

                                                                </div>
                                                            </div>
                                                        </div><?php 
                                                            
                                                    }?>
                                                </div>    
                                            </div><!-- end linesection goalies-->

                                            <?php 
                                            $field = api_get_line_arrays("otherfield");// Get the other page fields needed for the blocks.
                                            // Make the extra forwards and extra defense.
                                            foreach($field["start"] AS $fsid=>$fs){?>
                                                <div class="linesection id<?= api_MakeCSSClass($i)?> extra <?= $fs?>">
                                                    <h4>Extra <?= $fs?></h4>
                                                            <div class="blockcontainer">
                                                                <?php foreach($field["end"] AS $feid=>$fe){
                                                                    $usefield = "Extra" .$fsid . $fe;
                                                                    if(array_key_exists($usefield, $row)){?>
                                                                        <div class="positionline">
                                                                            <div class="paleText"><?= $fe?></div>
                                                                            <div class="positionname">
                                                                                <?php  $row[$usefield] = (isset($availableplayers[api_MakeCSSClass($row[$usefield])])) ? $row[$usefield] : "";?>
                                                                                <input id="<?= $usefield ?>" onclick="ChangePlayer('<?= $usefield ?>','<?= $league ?>',<?=$cpfields?>);" class="textName" readonly type="text" name="txtLine[<?= $usefield ?>]" value="<?= $row[$usefield] ?>">
                                                                            </div>
                                                                        </div><?php 
                                                                    }
                                                                }?>
                                                            </div><!--end blockcontainer -->
                                                        </div><!-- end linesection --><?php 
                                                    }?>
                                                    <?php // Make the penalty shots order?>
                                                    <div class="linesection id<?= api_MakeCSSClass($i)?> penaltyshots">
                                                        <h4>Penalty Shots</h4>
                                                        <div class="blockcontainer">								
                                                            <div class="penaltyshot">
                                                                <?php  for($x=1;$x<6;$x++){?>
                                                                <div class="positionline">
                                                                    <div class="paleText"><?= $x ?>.</div>
                                                                    <div class="positionname">
                                                                        <?php  $row["PenaltyShots" . $x] = (isset($availableplayers[api_MakeCSSClass($row["PenaltyShots" . $x])])) ? $row["PenaltyShots" . $x] : "";?>
                                                                        <input id="PenaltyShots<?= $x ?>" onclick="ChangePlayer('PenaltyShots<?= $x ?>','<?= $league ?>',<?=$cpfields?>);" class="textName" readonly type="text" name="txtLine[PenaltyShots<?= $x ?>]" value="<?= $row["PenaltyShots" . $x] ?>">
                                                                    </div>	
                                                                </div>
                                                                <?php }?>
                                                            </div>
                                                        </div><!-- end blockcontainer-->
                                                    </div><!-- end linesection penaltyshots-->
                                                <?php


//  TABS OT
                                        }else if($i == "OT"){ 
                                            foreach(array(10=>"Forward",5=>"Defense") AS $i=>$p){?>
                                            <div class="linesection card idot ot<?= $p?>">
                                                            <h4><?= $p?></h4>
                                                            <div class="blockcontainer">
                                                                <?php
                                                                for($x=1;$x<=$i;$x++){
                                                                    ?>
                                                                    <div class="positionline">
                                                                        <div class="positionlabel"><?= $x?>.</div>
                                                                        <div class="positionname">
                                                                            <?php  $row["OT" . $p.$x] = (isset($availableplayers[api_MakeCSSClass($row["OT" . $p.$x])])) ? $row["OT" . $p.$x] : "";?>															
                                                                            <input class="textName" id="OT<?= $p.$x;?>" onclick="ChangePlayer('OT<?= $p.$x;?>','<?= $league ?>',<?=$cpfields?>);"  readonly type="text" name="txtLine[OT<?=$p.$x;?>]" value="<?= $row["OT". $p.$x]; ?>">
                                                                        </div>
                                                                    </div><?php
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                    <?php	
                                                    }
                                        }else if($i == "Strategy"){?>
                                                        <h4>Team Wide Strategy</h4>
                                                                            <div class="strategieswrapper linesection">
                                                                                <?php
                                                                                    $text = "";
                                                                                     for($x=1;$x<=5;$x++){
                                                                                         if($x == 1 || $x == 2){$text = "If winning by ";}
                                                                                         else if($x == 4 || $x == 5){
                                                                                             $text = "If losing by";}
                                                                                         else{$text = "If the score is equal  ";
                                                                                         }
            
                                                                                         ?><div class="strategywrapper teamstrategy teamstrategy<?= $x; ?>">
                                                                                            <div class="strategyamount">
                                                                                                <?= $text ?> 
                                                                                                <?php if($x != 3){?><?php api_make_strategies($row,"Strategy". $x,"GoalDiff","Int-10",$cpfields);?><?php } ?>
                                                                                                then strategy is 
                                                                                            </div>
                                                                                            <div class="strategystrategies">
                                                                                                <div class="">
                                                                                                    <div class="strategy">
                                                                                                        <?php foreach($strategy AS $sid=>$strat){?>
                                                                                                            <div class="strats">
                                                                                                                <div class="stratlabel"><?= $sid?> : </div>
                                                                                                                <div class="stratvalue">
                                                                                                                    <?php api_make_strategies($row,"Strategy" .$x,$sid,"Strat",$cpfields);?>
                                                                                                                </div>
                                                                                                            </div>
                                                                                                        <?php }?>
                                                                                                    </div><!-- end strategy-->
                                                                                                </div><!-- end strategywrapper-->
                                                                                            </div>
                                                                                        </div><?php
                                                                                     }
                                                                                ?>
                                                                                <div class="strategywrapper PullGoalerMinGoal">
                                                                                    <div class="strategyamount">Goalie Minimum # of Goals before Remove from Game</div>
                                                                                    <div class="strategystrategies"><?php api_make_strategies($row,"PullGoaler","MinGoal","Int-10",$cpfields);?></div>
                                                                                </div>
                                                                                <div class="strategywrapper PullGoalerMinPct">
                                                                                    <div class="strategyamount">Goalie Save PCT Under before Remove from Game</div>
                                                                                    <div class="strategystrategies"><?php api_make_strategies($row,"PullGoaler","MinPct","Int-100",$cpfields);?></div>
                                                                                </div>
                                                                                <div class="strategywrapper RemoveGoalieSecond">
                                                                                    <div class="strategyamount">When to remove the goalies from goal if trailing by 1 in the third period (in seconds)</div>
                                                                                    <div class="strategystrategies"><?php api_make_strategies($row,"Remove","GoaliesSecond","Int-180",$cpfields);?></div>
                                                                                </div>
                                                                            </div>
                                                                        <?php
                                                                    }else{
                                                                        // Make the Offsensive and Defensive Lines.
                                                                        $types = array("Off"=>"Offensive Line","Def"=>"Defensive Line");
                                                                        foreach($types AS $tid=>$t){?>
                                                                            <div class="linesection id<?= api_MakeCSSClass($i)?> penaltyshots">
                                                                                <h4><?= $t?></h4>
                                                                                <div class="blockcontainer"><?php 
                                                                                    $fordef = array("Forward", "Defense");
                                                                                    foreach($fordef AS $fd){
                                                                                        foreach($positions[$fd] AS $pid=>$pos){
                                                                                            $usefield = "LastMin" . $tid . $fd . $pid;
                                                                                            if(array_key_exists($usefield, $row)){
                                                                                                ?>
                                                                                                <div class="positionline">
                                                                                                    <div class="positionlabel"><?= $pos?></div>
                                                                                                    <div class="positionname">
                                                                                                        <?php  $row[$usefield] = (isset($availableplayers[api_MakeCSSClass($row[$usefield])])) ? $row[$usefield]: "";?>
                                                                                                        <?= "<input id=\"". $usefield ."\" onclick=\"ChangePlayer('". $usefield ."','". $league ."',".$cpfields.");\" class=\"textName\" readonly type=\"text\" name=\"txtLine[". $usefield ."]\" value=\"". $row[$usefield] ."\">";?>
                                                                                                    </div>
                                                                                                </div><?php 
                                                                                            }
                                                                                        }
                                                                                    }?>
                                                                                </div><!-- end blockcontainer-->
                                                                            </div><!-- end linesection <?= api_MakeCSSClass($i)?> penaltyshots--><?php 
                                                                        }
                                                                    }?>
                                                                </div><!-- end tabs-<?= $count ?>--><?php 
                                                            }
                                                        }?>
                                              
                        </div><!-- end tabs-->
                    </div><!-- end linetabs-->
                </form>              
            
         

    </div><!-- end pagewrapper-->
</div><!-- end id lineeditor-->


        
        <div id="sideList" class="sideList">
            
            <?php
            // Get all the players and goalies of the team who are dressed
            $sql = api_sql_players_base("Player",$isPro);
            $sql .= "WHERE Team = " . $teamid . " AND Status1 = " . $status1 . " ";
            $sql .= "UNION ";
            $sql .= api_sql_players_base("Goaler",$isPro);
            $sql .= "WHERE Team = " . $teamid . " AND Status1 = " . $status1 . " ";
            $sql .= "ORDER BY Name ASC, Overall DESC ";
            ?>
            <div class="col-12">
                <button type="button" class="btn btn-warning btn-block" onclick="closeList()"> X </button>
            </div>
            <div class="alert alert-info darkText p-1"> <i class="fas fa-info-circle"></i> Double Click to see Player Info card </div> 
            <div class='row justify-content-center'>
                <div class='col-10  d-flex justify-content-center'>
                     <?php api_html_checkboxes_positionlist("sltPlayerList","true","list-item",null,null); ?>
                </div> 
            </div> 
             
            
            <div class="playerlist justify-content-start p-0">
                
                <form name="frmPlayerList">                
                    <ul class="playerselect list-group">
                        <?php 
                        $oRS = $db->query($sql);
                        $first = true;
                        while($row = $oRS->fetchArray()) {
                            if($first){$s = " checked"; $first = false;} else {$s = "";}
                            $values = api_fields_input_values($row); ?>
                            
                            <li id="line1_<?= api_MakeCSSClass($row["Name"])?>" class="option list-group-item" data-id="<?= $row['Number'] ?>">
                                <input name="sltPlayerList" type="radio" id="a<?= api_MakeCSSClass($row["Name"]); ?>" <?= $s;?> value="<?= $values; ?>">
                                <label for="a<?= api_MakeCSSClass($row["Name"]); ?>"><?= $row['Name']; ?> - <?= $row['PositionString']; ?> 
                                    <span class=""><small>(<?= $row['Overall']; ?> OV)</small></span>
                                </label>
                            
                            </li>
                        <?php } ?>
                        <li class="option">
                            <input name="sltPlayerList" type="radio" id="aRemove" value="">
                            <label for="aRemove">Remove Player/Goalie</label>
                        </li>
                    </ul>
                </form><!-- end frmPlayerList-->

            </div><!-- end playerlist-->

        </div>
        
                  

</header>                






<!-- Side Navigation -->
<div id="sideNavR" class="sidenavR">
  <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
  <div id="sideNavContent">PlayerCardC will appear here...</div>
</div>
            
            
            
<script>
    function deactivateBanners() {
        const banners = document.querySelectorAll('.banner'); 
        banners.forEach(banner => { banner.style.display = 'none'; // Hides the banner 
        });
    }
   
    setTimeout(deactivateBanners, 5000);                    // Hide the confirm banner after 5 seconds
    document.addEventListener('click', deactivateBanners);  // Hide the confirm banner on user interaction
</script>



<div id="playerInfoContainer"></div>            


<?php 
            }
		}else{
			echo "<div class=\"STHSDivInformationMessage\">" . $NoUserLogin . "<br /><br /></div>";		
		}


?>

  

<?php
	// Close the db connection
	$db->close();
       
}?>
    
<?php include ("Footer.php"); ?>

<script>

const TeamProInfo = <?php echo json_encode($TeamProInfo); ?>;
console.log(TeamProInfo)

const TeamPlayerInfo = <?php echo json_encode($TeamPlayerInfo); ?>;
console.log(TeamPlayerInfo)


</script>


</body></html>









