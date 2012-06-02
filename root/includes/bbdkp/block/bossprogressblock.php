<?php

/* bossprogress block
 * @package bbDkp
 * @copyright 2009 bbdkp <http://code.google.com/p/bbdkp/>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * 
*/
if (! defined ( 'IN_PHPBB' ))
{
	exit ();
}
$bpshow = false;
$user->add_lang ( array ('mods/dkp_admin',  'mods/dkp_bossprogress' ));

/*
 * installed games
 */
$games = array(
    'wow'        => $user->lang['WOW'], 
    'lotro'      => $user->lang['LOTRO'], 
    'eq'         => $user->lang['EQ'], 
    'daoc'       => $user->lang['DAOC'], 
    'vanguard' 	 => $user->lang['VANGUARD'],
    'eq2'        => $user->lang['EQ2'],
    'warhammer'  => $user->lang['WARHAMMER'],
    'aion'       => $user->lang['AION'],
    'FFXI'       => $user->lang['FFXI'],
	'rift'       => $user->lang['RIFT'],
	'swtor'      => $user->lang['SWTOR']
);

$installed_games = array();
foreach($games as $id => $gamename)
{
	if ($config['bbdkp_games_' . $id] == 1)
	{
		$installed_games[$id] = $gamename; 

		$template->assign_block_vars ( 'game', array (
			'U_BPIMG' 	=> "{$phpbb_root_path}images/bossprogress/{$id}/{$id}.png", 
			'GAME_ID' 	=> $id, 
			'GAME_NAME' => $gamename));
		display_bpblock($id, $gamename);
	} 
		
}

$template->assign_vars ( array (
		'S_SHOWPROGRESSBAR' => ($config['bbdkp_bp_blockshowprogressbar']==1 ? true:false) , 
		'S_BPSHOW' => $bpshow ));

// end

function display_bpblock($game_id, $game_name)
{
	global $phpbb_root_path, $phpEx, $config, $template, $db, $user;
	
	if ($config['bbdkp_bp_hidenewzone'] == 1)
	{
		// inner join to hide zones with no bosses killed
		$sql_array = array(
		   'SELECT'    => 	' z.id as zoneid, 
		   					  l.name as zonename, 
		   					  l.name_short as zonename_short, 
		   					  z.completed ',
		   'FROM'      => array(
				ZONEBASE 		=> 'z',
				BB_LANGUAGE 	=> 'l',
				BOSSBASE		=> 'b', 
					),
			'WHERE'		=> " z.id = l.attribute_id 
				AND l.attribute='zone' AND l.language= '" . $config['bbdkp_lang'] ."'
				AND z.showzoneportal = 1  
				AND z.game = l.game_id 
				AND b.game = z.game
				AND z.game= '" . $game_id . "'
				AND b.zoneid = z.id and b.killed = 1",
			'GROUP_BY'	=> 'z.id, l.name, l.name_short, z.completed',
			'ORDER_BY'	=> 'z.sequence desc, z.id desc ',
		   );
	}
	else 
	{
		$sql_array = array(
		   'SELECT'    => 	' z.id as zoneid, 
		   					  l.name as zonename, 
		   					  l.name_short as zonename_short, 
		   					  z.completed ',
		   'FROM'      => array(
				ZONEBASE 		=> 'z',
				BB_LANGUAGE 	=> 'l',
					),
			'WHERE'		=> " z.id = l.attribute_id 
				AND l.attribute='zone' AND l.language= '" . $config['bbdkp_lang'] ."'
				AND z.game = l.game_id 
				AND z.showzoneportal = 1  
				AND z.game= '" . $game_id . "'",
			'ORDER_BY'	=> 'z.sequence desc, z.id desc ',
		   );
	}
	
	$sql = $db->sql_build_query ( 'SELECT', $sql_array );
	$result = $db->sql_query ( $sql );
	$i = 0;
	$zones = array();
	while ( $row = $db->sql_fetchrow ( $result ) )
	{
		$bpshow = true;
		$zones [$i] = array (
			'zoneid' => $row ['zoneid'], 
			'zonename' => $row ['zonename'], 
			'zonename_short' => $row ['zonename_short'], 
			'completed' => $row ['completed'] );
		
		$sql_array = array(
		    'SELECT'    => 	' b.id, l.name as bossname, l.name_short as bossname_short, b.imagename, 
		    b.webid, b.killed, b.killdate, b.counter, b.showboss, b.zoneid  ', 
		    'FROM'      => array(
		        BOSSBASE 		=> 'b',
	            BB_LANGUAGE 	=> 'l',
		    	),
		    'WHERE'		=> 'b.zoneid = ' . $row ['zoneid'] . " AND b.id = l.attribute_id 
		    AND b.showboss=1 AND b.game = l.game_id AND b.game = '" . $game_id ."' 
		    AND l.attribute='boss' AND l.language= '" . $config['bbdkp_lang'] ."'",
			'ORDER_BY'	=> 'b.zoneid, b.id ASC ',
		    );	
		    
		// skip new bosses?
		if ($config['bbdkp_bp_hidenonkilled'] == 1 )
		{
			$sql_array['WHERE'] .= ' AND b.killed = 1 '; 
		}
		
		$bosskill=0;
		$boss = array();
		$j = 0;
		$sql2 = $db->sql_build_query ( 'SELECT', $sql_array );
		$result2 = $db->sql_query ( $sql2 );
		while ( $row2 = $db->sql_fetchrow ( $result2 ) )
		{
			$boss[$j] = array( 
				'bossid' 		 => $row2 ['id'], 
				'bossname' 		 => $row2 ['bossname'], 
				'bossname_short' => $row2 ['bossname_short'], 
				'killed'  		 => $row2 ['killed'], 
				'url' 			 => sprintf($user->lang[strtoupper($game_id).'_BASEURL'], $row2 ['webid'])
			 ); 
			 if ($row2 ['killed'] == 1)
			 {
				$bosskill++;	 
			 }
			 $j++;
		}
		
		$zones[$i]['bosses'] = (array) $boss; 
		$zones[$i]['bosscount'] = $j;
		$zones[$i]['bosskills'] = $bosskill; 
		$zones[$i]['completed'] = ($j>0) ? round($bosskill/$j,2)*100 : 0;
	  	if ((int)$zones[$i]['completed']  <= 0) 
	 		{
			$zones[$i]['cssclass'] = 'bpprogress00';
	  	}
		elseif ((int)$zones[$i]['completed'] <= 25) 
		{
			$zones[$i]['cssclass'] = 'bpprogress25';
		}
		elseif ((int)$zones[$i]['completed'] <= 50) 
		{
			$zones[$i]['cssclass'] = 'bpprogress50';
		}
		elseif ((int)$zones[$i]['completed'] <= 75) 
		{	
			$zones[$i]['cssclass'] = 'bpprogress75';
		}
		elseif ((int)$zones[$i]['completed'] <= 99) 
		{
			$zones[$i]['cssclass'] = 'bpprogress99';
		}
		elseif ((int)$zones[$i]['completed'] >= 100) 
		{
			$zones[$i]['cssclass'] = 'bpprogress100';
		}
		unset ($boss);
		$i++;
		$db->sql_freeresult ($result2);
	}
	$db->sql_freeresult ($result);	
		
	foreach($zones as $key => $zone)
	{
		$template->assign_block_vars('game.zone', array(
				'ZONEID'  		=> $zone['zoneid'],
				'CSSCLASS'  	=> $zone['cssclass'],
				'ZONENAME' 		=> $zone['zonename'],
				'BOSSKILLS'		=> $zone['bosskills'], 
				'BOSSCOUNT'		=> $zone['bosscount'],
				'COMPLETED' 	=> $zone['completed'],
		));
	
		foreach($zone['bosses'] as $key => $boss)
		{
			$a = 1;
			$template->assign_block_vars('game.zone.boss', array(
					'BOSSNAME'  	=> $boss['bossname'],
					'KILLED'  		=> $boss['killed'],
					'BOSSURL'  		=> $boss['url'],
			));
		}
	}
	
		
}

?>