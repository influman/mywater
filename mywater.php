<?php  
            $xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>";  
	       //**********************************************************************************************************
            // V1.4 : Script de suivi de la consommation en eau
            //*************************************** ******************************************************************
            // recuperation des infos depuis la requete
            // API CONSO CUMULEE - VAR1
            $api_cumul = getArg("apic", $mandatory = true, $default = 'undefined');
			// UNITE - VAR2 (m3, litres)
            $unite = getArg("unit", $mandatory = true, $default = 'm3');
            // DELTA COMPTEUR REEL - VAR3
            $delta = getArg("delta", $mandatory = false, $default = '0-0');
            // action
            $action = getArg("action", $mandatory = true, $default = '');
            // type
            $type = getArg("type", $mandatory = false, $default = '');
            // valeur passée en argument
            $arg_value = getArg("value", $mandatory = false, $default = '');
			// API DU PERIPHERIQUE APPELANT LE SCRIPT
            $api_script = getArg('eedomus_controller_module_id'); 
 
            $xml .= "<MYWATER>";
			if ($action == 'updatetarif' || $action == 'updateconso') {
				$maintenant = date("H").":".date("i");
				$xml .= "<APPEL>".$maintenant." ".$api_script."</APPEL>";
			}
            // Ecart avec le conmpteur
            $delta_global = 0;
            if (!strpos($delta, "-")) {
                $delta_global = $delta;
                if ($delta_global == '') {
                    $delta_global = 0;
                }
            }
            $xml .= "<DELTA_GLOBAL>".$delta_global."</DELTA_GLOBAL>";      
            
			
			// s'assurer qu'il y a bien un compteur cumulé défini
			$type_cumul = false;
			if ($api_cumul != 'undefined' && $api_cumul != '' && $api_cumul != 'plugin.parameters.APIC') {
				if ($unite == "m3" || $unite == "litres") {
					$type_cumul = true;
					$api_compteur = $api_cumul;
					$xml .= "<COMPTEUR>CUMUL ".$api_compteur." (".$unite.")</COMPTEUR>";
				}
			}
			if (!$type_cumul) {
				$xml .= "<COMPTEUR>INCONNU</COMPTEUR>";
			}
			
		// Un compteur a été paramétré
		// Initialisation des données
		if ($type_cumul) {
			if ($action == 'updatetarif' || $action == 'updateconso') {
            	// CHARGEMENT DES VARIABLES CODES API du périphérique Abonnement
            	// et définition du mode tarifaire en cours
            	$global = false;
            	$mesure = "PAS DE MESURE";
            	$abo_ok = false;
            	$aboglobal = false;
				$aboraz = false;
            	$tarif_dev = 0;
				$abobase = '';
				$tab_api_cpt_ok = false;
				$tab_api_cpt_init = array ("jour_global" => 0, "jour_prec_global" => 0, 
								   "mois_global" => 0, "mois_prec_global" => 0,
								   "annee_global" => 0, "annee_prec_global" => 0, "cpt_delta_global" => 0);
            	if (loadVariable('MYWATERGAPI_ABO_'.$api_compteur) != '') {
				// charge l'api du capteur tarif
                    $api_abo = loadVariable('MYWATERGAPI_ABO_'.$api_compteur);
                    if (loadVariable('MYWATERG_ABO_'.$api_compteur) != '') {
						$abobase = loadVariable('MYWATERG_ABO_'.$api_compteur);
						$abo_ok = true;
						if (!strpos($abobase, ".")) {
							$aboraz = true;
						} else {
							$aboglobal = true;
							$global = true;
							$mesure = "";
							$tarif_dev = $abobase;
						}
					}
				}
				
				if (loadVariable('MYWATERGAPI_CPT_'.$api_compteur) != '') {
				// charge le tableau des API des différents compeuts J, J-1...
                    $tab_api_current_cpt = loadVariable('MYWATERGAPI_CPT_'.$api_compteur);
                    if ($tab_api_current_cpt['jour_global'] != 0 and $tab_api_current_cpt['mois_global'] != 0 and $tab_api_current_cpt['annee_global'] != 0) {
						$tab_api_cpt_ok = true;
					}
					else {
						$tab_api_current_cpt = $tab_api_cpt_init;
						saveVariable('MYWATERGAPI_CPT_'.$api_compteur, $tab_api_current_cpt);
					}
				}
				else {
					$tab_api_current_cpt = $tab_api_cpt_init;
					saveVariable('MYWATERGAPI_CPT_'.$api_compteur, $tab_api_current_cpt);
				}
			}
			// ********************************************************************************************
            // lecture/maj des capteurs Abonnement associé à ce compteur (cumulé ou instantané)
            if ($action == 'updatetarif') {
            	 // lui est un actionneur, on stocke son code API et on récupère la valeur
            	if ($type == 'abo' && $arg_value != '') {
            	    		
					if ($arg_value == 'poll') {
						if ($abo_ok) {
							$abo = $abobase;
						} else {
							$abo = "Sélectionner prix m3...";
						}
						$xml .= "<ABO>".$abo."</ABO>";
					} else {
						$abo = $arg_value;
						// enregistrement de l'api de l'abonnement
						saveVariable('MYWATERGAPI_ABO_'.$api_compteur, $api_script);
						if (!strpos($abo, ".")) {
							$abo = '';
						} else {
							// enregistrement de la valeur de l'abonnement
							saveVariable('MYWATERG_ABO_'.$api_compteur, $abo);
						}
						die();
					}
                }
            }
			//**********************************************************************************
			// Mise à jour de la consommation
            if ($action == 'updateconso') {
				// restitution de la valeur actuel du compteur
            	$value = getValue($api_compteur);
            	$etat_compteur = $value['value'];
            	$xml .= "<VALCOMPTEUR>".$etat_compteur."</VALCOMPTEUR>";
            	$releve_conso = 0;
            	// restitution du précédent relevé du compteur (si état cumul)
				$dernier_releve = $etat_compteur;
				if (loadVariable('MYWATERG_LASTRELEVE_'.$api_compteur) != '') {
					$dernier_releve = loadVariable('MYWATERG_LASTRELEVE_'.$api_compteur);
				} 
				$xml .= "<LASTVALCOMPTEUR>".$dernier_releve."</LASTVALCOMPTEUR>";
				// si compteur < dernier relevé
				if ($etat_compteur < $dernier_releve) {
					$releve_conso = round(($etat_compteur / 1000), 4);
					if ($unite == "m3") {
						$releve_conso = round($etat_compteur, 4);
					}
				}
				else {
					$releve_conso = round((($etat_compteur - $dernier_releve) / 1000), 4);
					if ($unite == "m3") {
						$releve_conso = round(($etat_compteur - $dernier_releve), 4);
					}
				}
				// mise à jour dernier relevé
				saveVariable('MYWATERG_LASTRELEVE_'.$api_compteur, $etat_compteur);
						
				if (loadVariable('MYWATERG_CPT_'.$api_compteur) != '') {
					$tab_cpt = loadVariable('MYWATERG_CPT_'.$api_compteur);
				} else {
					$tab_cpt['global'] = 0;
				}
				// toujours "global" pour compteur d'eau
				if ($global) {
					$tab_cpt['global'] = $etat_compteur;
				}		
				// cout du relevé en m3
				$cout = round(($releve_conso * (double)$tarif_dev), 6);
				// chargement des mesures précédentes
				if (loadVariable('MYWATERG_RELEVES_'.$api_compteur) != '') {
					$tab_releves = loadVariable('MYWATERG_RELEVES_'.$api_compteur);
				} else {
					$tab_releves = array ("jour_global" => 0.0000, "jour_prec_global" => 0.0000, 
										"mois_global" => 0.0000, "mois_prec_global" => 0.0000,
										"annee_global" => 0.0000, "annee_prec_global" => 0.0000,
										"lastmesure" => date('d')."-00:00");
				}
				$lasttime = substr($tab_releves['lastmesure'], 3, 5);
				$lastday = substr($tab_releves['lastmesure'], 0, 2);
				$razday = false;
				$razmois = false;
				$razannee = false;
				// si dernière mesure veille
				if ($lastday != date('d')) {
					$razday = true;
					if (date('j') == 1) {
						$razmois = true;
					}
					if (date('n') == 1 && $razmois) {
						$razannee = true;
					}
				}
				if (loadVariable('MYWATERG_COUTS_'.$api_compteur) != '') {
					$tab_couts = loadVariable('MYWATERG_COUTS_'.$api_compteur);
				} else {
					$tab_couts = array ("jour_global" => 0.000000, "jour_prec_global" => 0.000000, 
										"mois_global" => 0.000000, "mois_prec_global" => 0.000000,
										"annee_global" => 0.000000, "annee_prec_global" => 0.000000);
				}
				$releve_jour_global = $tab_releves['jour_global'];
				$releve_jour_prec_global = $tab_releves['jour_prec_global'];
				$releve_mois_global = $tab_releves['mois_global'];
				$releve_mois_prec_global = $tab_releves['mois_prec_global'];
				$releve_annee_global = $tab_releves['annee_global'];
				$releve_annee_prec_global = $tab_releves['annee_prec_global'];
				// ajout de la consommation au compteur respectif, releve et cout
				if ($global) {
					$releve_jour_global += $releve_conso;
					$releve_mois_global += $releve_conso;
					$releve_annee_global += $releve_conso;
					$tab_couts['jour_global'] += $cout;
					$tab_couts['mois_global'] += $cout;
					$tab_couts['annee_global'] += $cout;
				}
						
				// chargement prévisionnel annuel
				$prevannuel = "...";
				if (loadVariable('MYWATERG_PREV_'.$api_compteur) != '') {
					$prevannuel = loadVariable('MYWATERG_PREV_'.$api_compteur);
				}
				
				// REMISES A ZERO
				if ($razday) {
					$nbprevcoutj = 0;
					$releve_jour_prec_global = $releve_jour_global;
					$prevcoutj = $tab_couts['jour_prec_global'];
					if ($prevcoutj > 0) {
						$nbprevcoutj = 1;
					}
					$tab_couts['jour_prec_global'] = $tab_couts['jour_global'];
					$prevcoutj += $tab_couts['jour_prec_global'];
					$nbprevcoutj++;
					$releve_jour_global = 0;
					$tab_couts['jour_global'] = 0;
				}
				
				if ($razmois) {
					$nbprevcout = 0;
					$releve_mois_prec_global = $releve_mois_global;
					$prevcout = $tab_couts['mois_prec_global'];
					if ($prevcout > 0) {
						$nbprevcout = 1;
					}
					$tab_couts['mois_prec_global'] = $tab_couts['mois_global'];
					$prevcout += $tab_couts['mois_prec_global'];
					$nbprevcout++;
					$releve_mois_global = 0;
					$tab_couts['mois_global'] = 0;
				}
				if ($razannee) {
					$releve_annee_prec_global = $releve_annee_global;
					$tab_couts['annee_prec_global'] = $tab_couts['annee_global'];
					$releve_annee_global = 0;
					$tab_couts['annee_global'] = 0;
				}
					
				$tab_releves['jour_global'] = $releve_jour_global;
				$tab_releves['jour_prec_global'] = $releve_jour_prec_global;
				$tab_releves['mois_global'] = $releve_mois_global;
				$tab_releves['mois_prec_global'] = $releve_mois_prec_global;
				$tab_releves['annee_global'] = $releve_annee_global;
				$tab_releves['annee_prec_global'] = $releve_annee_prec_global;
				$tab_releves['lastmesure'] = date('d')."-".$maintenant;
				saveVariable('MYWATERG_RELEVES_'.$api_compteur, $tab_releves);
				saveVariable('MYWATERG_COUTS_'.$api_compteur, $tab_couts);
				saveVariable('MYWATERG_CPT_'.$api_compteur, $tab_cpt);
				if ($global) {
					$mesure .= " ".$releve_jour_global." m3";
				}
				$prevcalc = 0;
				if ($nbprevcoutj > 0) {
					$prevcalc = round($prevcoutj * 365 / $nbprevcoutj,2);
					if ($prevannuel == "...") {
						$prevannuel = $prevcalc;
					} 
				}
					
				if ($nbprevcout > 0) {
					$prevcalc = round($prevcout * 12 / $nbprevcout,2);
				}
				if ($prevcalc > $prevannuel) {
					$prevannuel = $prevcalc;
				}
					
				saveVariable('MYWATERG_PREV_'.$api_compteur, $prevannuel);
				$mesure .= " (prev. ".$prevannuel." eur/an max)";
				$xml .= "<STATUT>".$mesure."</STATUT>";
					
				// Mise à jour hors polling des compteurs J, J-1...
				if ($tab_api_cpt_ok) {
					setValue($tab_api_current_cpt['jour_global'], round(releve_jour_global,3)."m3 (".round($tab_couts['jour_global'],3)."eur", $update_only = true);
					setValue($tab_api_current_cpt['mois_global'], round($releve_mois_global,3)."m3 (".round($tab_couts['mois_global'],3)."eur", $update_only = true);
					setValue($tab_api_current_cpt['annee_global'], round($releve_annee_global,3)."m3 (".round($tab_couts['annee_global'],3)."eur", $update_only = true);
					if ($tab_api_current_cpt['jour_prec_global'] != 0) {
						setValue($tab_api_current_cpt['jour_prec_global'], round($releve_jour_prec_global,3)."m3 (".round($tab_couts['jour_prec_global'],3)."eur", $update_only = true);
					}
					if ($tab_api_current_cpt['mois_prec_global'] != 0) {
						setValue($tab_api_current_cpt['mois_prec_global'], round($releve_mois_prec_global,3)."m3 (".round($tab_couts['mois_prec_global'],3)."eur", $update_only = true);
					}
					if ($tab_api_current_cpt['annee_prec_global'] != 0) {
						setValue($tab_api_current_cpt['annee_prec_global'], round($releve_annee_prec_global,3)."m3 (".round($tab_couts['annee_prec_global'],3)."eur", $update_only = true);
					}
					if ($tab_api_current_cpt['cpt_delta_global'] != 0) {
							setValue($tab_api_current_cpt['cpt_delta_global'], $tab_cpt['global'] + $delta_global, $update_only = true);
					}
		       	}
			}
		} else if ($action == 'updateconso') {
	    	$xml .= "<STATUT>En attente compteur...</STATUT>";
	    } else if ($action == 'updatetarif' ) {
	    	if ($type == 'abo') {
	    		$xml .= "<ABO>En attente compteur...</ABO>";
	    	}
	    }
		
		// ***********************************************************************************
        // lecture des capteurs
        if ($action == 'read') {
            $cpt = $delta_global;
            $tab_init = array ("jour_global" => 0.0000, "jour_prec_global" => 0.0000, 
								   "mois_global" => 0.0000, "mois_prec_global" => 0.0000,
								   "annee_global" => 0.0000, "annee_prec_global" => 0.0000,
								   "lastmesure" => date('d')."-00:00");
            // restitution de la valeur actuel du compteur
            if (loadVariable('MYWATERG_RELEVES_'.$api_compteur) != '') {
            	$tab_init = loadVariable('MYWATERG_RELEVES_'.$api_compteur);
			}
            $xml .= "<JOUR_GLOBAL>".round($tab_init['jour_global'],3)."</JOUR_GLOBAL>";
            $xml .= "<MOIS_GLOBAL>".round($tab_init['mois_global'],3)."</MOIS_GLOBAL>";
            $xml .= "<ANNEE_GLOBAL>".round($tab_init['annee_global'],3)."</ANNEE_GLOBAL>";
            $xml .= "<ANNEE_PREC_GLOBAL>".round($tab_init['annee_prec_global'],3)."</ANNEE_PREC_GLOBAL>";
            $xml .= "<JOUR_PREC_GLOBAL>".round($tab_init['jour_prec_global'],3)."</JOUR_PREC_GLOBAL>";
            $xml .= "<MOIS_PREC_GLOBAL>".round($tab_init['mois_prec_global'],3)."</MOIS_PREC_GLOBAL>";
			$xml .= "<LASTMESURE>".round($tab_init['lastmesure'],3)."</LASTMESURE>";
				
            if ($type_cumul) {
				if (loadVariable('MYWATERG_CPT_'.$api_compteur) != '') {
					$tab_cpt = loadVariable('MYWATERG_CPT_'.$api_compteur);
					$cpt = $tab_cpt['global'] + $delta_global;
				} 
            }
            $xml .= "<CPT_DELTA_GLOBAL>".$cpt."</CPT_DELTA_GLOBAL>";
            
            	
            $tab_initc = array ("jour_global" => 0.000000, "jour_prec_global" => 0.000000, 
								   "mois_global" => 0.000000, "mois_prec_global" => 0.000000,
								   "annee_global" => 0.000000, "annee_prec_global" => 0.000000);
			// restitution de la valeur actuel des couts
            if (loadVariable('MYWATERG_COUTS_'.$api_compteur) != '') {
            	$tab_initc = loadVariable('MYWATERG_COUTS_'.$api_compteur);
           	}
            $xml .= "<JOUR_GLOBALC>".round($tab_initc['jour_global'],3)."</JOUR_GLOBALC>";
            $xml .= "<MOIS_GLOBALC>".round($tab_initc['mois_global'],3)."</MOIS_GLOBALC>";
            $xml .= "<ANNEE_GLOBALC>".round($tab_initc['annee_global'],3)."</ANNEE_GLOBALC>";
            $xml .= "<ANNEE_PREC_GLOBALC>".round($tab_initc['annee_prec_global'],3)."</ANNEE_PREC_GLOBALC>";
            $xml .= "<JOUR_PREC_GLOBALC>".round($tab_initc['jour_prec_global'],3)."</JOUR_PREC_GLOBALC>";
			$xml .= "<MOIS_PREC_GLOBALC>".round($tab_initc['mois_prec_global'],3)."</MOIS_PREC_GLOBALC>";
            
			if ($arg_value != '') {
				if (loadVariable('MYWATERGAPI_CPT_'.$api_compteur) != '') {
				// charge le tableau des API des différents compeuts J, J-1...
                    $tab_api_current_cpt = loadVariable('MYWATERGAPI_CPT_'.$api_compteur);
					$maj_tab_cpt = false;
                   	if ($arg_value == "jour_global" and $tab_api_current_cpt['jour_global'] != $api_script) {
						$tab_api_current_cpt['jour_global'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "mois_global" and $tab_api_current_cpt['mois_global'] != $api_script) {
						$tab_api_current_cpt['mois_global'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "annee_global" and $tab_api_current_cpt['annee_global'] != $api_script) {
						$tab_api_current_cpt['annee_global'] = $api_script;
						$maj_tab_cpt = true;
					}		
					if ($arg_value == "jour_prec_global" and $tab_api_current_cpt['jour_prec_global'] != $api_script) {
						$tab_api_current_cpt['jour_prec_global'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "mois_prec_global" and $tab_api_current_cpt['mois_prec_global'] != $api_script) {
						$tab_api_current_cpt['mois_prec_global'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "annee_prec_global" and $tab_api_current_cpt['annee_prec_global'] != $api_script) {
						$tab_api_current_cpt['annee_prec_global'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($arg_value == "cpt_delta_global" and $tab_api_current_cpt['cpt_delta_global'] != $api_script) {
						$tab_api_current_cpt['cpt_delta_global'] = $api_script;
						$maj_tab_cpt = true;
					}
					if ($maj_tab_cpt) {
						saveVariable('MYWATERGAPI_CPT_'.$api_compteur, $tab_api_current_cpt);
					}
				}
				
			}
        }
	    // ***********************************************************************************
        // mise à zéro manuelle
        if ($action == 'raz') {
			$tab_init = array ("jour_global" => 0.0000, "jour_prec_global" => 0.0000, 
								   "mois_global" => 0.0000, "mois_prec_global" => 0.0000,
								   "annee_global" => 0.0000, "annee_prec_global" => 0.0000,
								   "lastmesure" => date('d')."-00:00");
			$tab_initc = array ("jour_global" => 0.000000, "jour_prec_global" => 0.000000, 
								   "mois_global" => 0.000000, "mois_prec_global" => 0.000000,
								   "annee_global" => 0.000000, "annee_prec_global" => 0.000000);
			if (loadVariable('MYWATERG_RELEVES_'.$api_compteur) != '') {
				saveVariable('MYWATERG_RELEVES_'.$api_compteur, $tab_init);
            }		
            if (loadVariable('MYWATERG_COUTS_'.$api_compteur) != '') {
            	saveVariable('MYWATERG_COUTS_'.$api_compteur, $tab_initc);
            }		
            	
			if (loadVariable('MYWATERG_LASTRELEVE_'.$api_compteur) != '') {
				saveVariable('MYWATERG_LASTRELEVE_'.$api_compteur, 0);
			}	
			if (loadVariable('MYWATERG_CPT_'.$api_compteur) != '') {
				$tab_cpt = loadVariable('MYWATERG_CPT_'.$api_compteur);
				$tab_cpt['global'] = 0;
				saveVariable('MYWATERG_CPT_'.$api_compteur, $tab_cpt);
			}	
			if (loadVariable('MYWATERG_PREV_'.$api_compteur) != '') {
				saveVariable('MYWATERG_PREV_'.$api_compteur, "...");
			}
			die();
		}
		// mise à jour manuelle
        if ($action == 'maj') {
			$tab_reinit = array ("jour_global" => 0.0000, "jour_prec_global" => 0.0000, 
								   "mois_global" => 0.0000, "mois_prec_global" => 0.0000,
								   "annee_global" => 0.0000, "annee_prec_global" => 0.0000,
								   "lastmesure" => date('d')."-00:00");
			$tab_reinitc = array ("jour_global" => 0.000000, "jour_prec_global" => 0.000000, 
								   "mois_global" => 0.000000, "mois_prec_global" => 0.000000,
								   "annee_global" => 0.000000, "annee_prec_global" => 0.000000);
				
			$xml .= "<MAJ>".$type." - ".$arg_value."</MAJ>";
			if (loadVariable('MYWATERG_RELEVES_'.$api_compteur) != '') {
				$tab_reinit= loadVariable('MYWATERG_RELEVES_'.$api_compteur);
				if ($type == 'JOUR_GLOBAL' && $arg_value != "") {
					$tab_reinit['jour_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'JOUR_PREC_GLOBAL' && $arg_value != "") {
					$tab_reinit['jour_prec_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'MOIS_GLOBAL' && $arg_value != "") {
					$tab_reinit['mois_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'MOIS_PREC_GLOBAL' && $arg_value != "") {
					$tab_reinit['mois_prec_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_GLOBAL' && $arg_value != "") {
					$tab_reinit['annee_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_PREC_GLOBAL' && $arg_value != "") {
					$tab_reinit['annee_prec_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				
				saveVariable('MYWATERG_RELEVES_'.$api_compteur, $tab_reinit);
				
			}
            		
		
				
			if (loadVariable('MYWATERG_COUTS_'.$api_compteur) != '') {
				$tab_reinitc = loadVariable('MYWATERG_COUTS_'.$api_compteur);
				if ($type == 'JOUR_GLOBALC' && $arg_value != "") {
					$tab_reinitc['jour_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'JOUR_PREC_GLOBALC' && $arg_value != "") {
					$tab_reinitc['jour_prec_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'MOIS_GLOBALC' && $arg_value != "") {
					$tab_reinitc['mois_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'MOIS_PREC_GLOBALC' && $arg_value != "") {
					$tab_reinitc['mois_prec_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_GLOBALC' && $arg_value != "") {
					$tab_reinitc['annee_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				if ($type == 'ANNEE_PREC_GLOBALC' && $arg_value != "") {
					$tab_reinitc['annee_prec_global'] = $arg_value;
					$xml .= "<MAJ_RESULT>OK</MAJ_RESULT>";
				}
				saveVariable('MYWATERG_COUTS_'.$api_compteur, $tab_reinitc);
			}
					
		}
         
		// migration v1 à v2
        if ($action == 'migrate') {
			$tab_reinit = array ("jour_global" => 0.0000, "jour_prec_global" => 0.0000, 
								   "mois_global" => 0.0000, "mois_prec_global" => 0.0000,
								   "annee_global" => 0.0000, "annee_prec_global" => 0.0000,
								   "lastmesure" => date('d')."-00:00");
			$tab_reinitc = array ("jour_global" => 0.000000, "jour_prec_global" => 0.000000, 
								   "mois_global" => 0.000000, "mois_prec_global" => 0.000000,
								   "annee_global" => 0.000000, "annee_prec_global" => 0.000000);
				
			
			if (loadVariable('MYWATERG_RELEVES') != '') {
				$tab_releves = loadVariable('MYWATERG_RELEVES');
				if (array_key_exists($api_compteur, $tab_releves)) {
					$tab_reinit = $tab_releves[$api_compteur];
					saveVariable('MYWATERG_RELEVES_'.$api_compteur, $tab_reinit);
					
					if (loadVariable('MYWATERG_COUTS') != '') {
						$tab_couts= loadVariable('MYWATERG_COUTS');
						if (array_key_exists($api_compteur, $tab_couts)) {
							$tab_reinitc = $tab_couts[$api_compteur];
						}
						saveVariable('MYWATERG_COUTS_'.$api_compteur, $tab_reinitc);
					}
					
					if (loadVariable('MYWATERG_CPT') != '') {
						$tab_cpt = loadVariable('MYWATERG_CPT');
						if (array_key_exists($api_compteur, $tab_cpt)) {
							saveVariable('MYWATERG_CPT_'.$api_compteur, $tab_cpt[$api_compteur]);
						}
					} 
					
					if (loadVariable('MYWATERG_LASTRELEVE') != '') {
						$tab_dernierreleve = loadVariable('MYWATERG_LASTRELEVE');
						if (array_key_exists($api_compteur, $tab_dernierreleve)) {
							saveVariable('MYWATERG_LASTRELEVE_'.$api_compteur, $tab_dernierreleve[$api_compteur]);
						}
					} 
					$xml .= "<STATUT>MIGRATION OK</STATUT>";
				}
			}
		}
		
	    $xml .= "</MYWATER>";
		sdk_header('text/xml');
		echo $xml;
?>
