<?php

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2003-2009 the SysCP Team (see authors).
 * Copyright (c) 2010 the Froxlor Team (see authors).
 *
 * For the full copyright and license information, please view the COPYING
 * file that was distributed with this source code. You can also view the
 * COPYING file online at http://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright  (c) the authors
 * @author     Florian Lippert <flo@syscp.org> (2003-2009)
 * @author     Froxlor team <team@froxlor.org> (2010-)
 * @license    GPLv2 http://files.froxlor.org/misc/COPYING.txt
 * @package    Cron
 * @version    $Id$
 */

/*
 * necessary includes
 */
require_once(makeCorrectFile(dirname(__FILE__) . '/cron_tasks.inc.dns.10.bind.php'));
require_once(makeCorrectFile(dirname(__FILE__) . '/cron_tasks.inc.http.10.apache.php'));
require_once(makeCorrectFile(dirname(__FILE__) . '/cron_tasks.inc.http.15.apache_fcgid.php'));
require_once(makeCorrectFile(dirname(__FILE__) . '/cron_tasks.inc.http.20.lighttpd.php'));
require_once(makeCorrectFile(dirname(__FILE__) . '/cron_tasks.inc.http.25.lighttpd_fcgid.php'));

/**
 * LOOK INTO TASKS TABLE TO SEE IF THERE ARE ANY UNDONE JOBS
 */

fwrite($debugHandler, '  cron_tasks: Searching for tasks to do' . "\n");
$cronlog->logAction(CRON_ACTION, LOG_INFO, "Searching for tasks to do");
$result_tasks = $db->query("SELECT `id`, `type`, `data` FROM `" . TABLE_PANEL_TASKS . "` ORDER BY `id` ASC");
$resultIDs = array();

while($row = $db->fetch_array($result_tasks))
{
	$resultIDs[] = $row['id'];

	if($row['data'] != '')
	{
		$row['data'] = unserialize($row['data']);
	}

	/**
	 * TYPE=1 MEANS TO REBUILD APACHE VHOSTS.CONF
	 */

	if($row['type'] == '1')
	{
		//dhr: cleanout froxlor-generated awstats configs prior to re-creation
		if ($settings['system']['awstats_enabled'] == '1')
		{
			$awstatsclean['header'] = "## GENERATED BY FROXLOR\n";
			$awstatsclean['headerold'] = "## GENERATED BY SYSCP\n";
			$awstatsclean['path'] = '/etc/awstats';

			/**
			 * dont do anyting if the directory not exists
			 * (e.g. awstats not installed yet or whatever)
			 * fixes #45
			 */
			if (is_dir($awstatsclean['path'])) 
			{
				$awstatsclean['dir'] = dir($awstatsclean['path']);
				while($awstatsclean['entry'] = $awstatsclean['dir']->read()) {
					$awstatsclean['fullentry'] = $awstatsclean['path'].'/'.$awstatsclean['entry'];
					/**
					 * dont do anything if the file does not exist
					 */
					if (file_exists($awstatsclean['fullentry']))
					{
						$awstatsclean['fh'] = fopen($awstatsclean['fullentry'], 'r');
						$awstatsclean['headerRead'] = fgets($awstatsclean['fh'], strlen($awstatsclean['header'])+1);
						fclose($awstatsclean['fh']);
						if($awstatsclean['headerRead'] == $awstatsclean['header'] || $awstatsclean['headerRead'] == $awstatsclean['headerold']) {
							$cronlog->logAction(CRON_ACTION, LOG_INFO, "Removing awstats configuration ".$awstatsclean['fullentry']." for re-creation");
							@unlink($awstatsclean['fullentry']);
						}
					}
					else
					{
						$cronlog->logAction(CRON_ACTION, LOG_WARNING, "File '".$awstatsclean['fullentry']."' could not be found, please check if you followed all the instructions on the configuration page");
					}
				}
			}
			unset($awstatsclean);
		}
		//end dhr

		// clear fcgid - starter files prior to re-creation to keep it clean, #367
		if ($settings['system']['mod_fcgid'] == '1')
		{
			$configdir = makeCorrectDir($settings['system']['mod_fcgid_configdir']);

			if (is_dir($configdir)) 
			{
				$its = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($configdir)
				);

				// iterate through all subdirs,
				// look for php-fcgi-starter files
				// and take immutable-flag away from them
				// so we can delete them :)
				foreach ($its as $fullFileName => $it ) 
				{
					if ($it->isFile() && $it->getFilename() == 'php-fcgi-starter') 
					{
						removeImmutable($its->getPathname());
					}
				}
				// now get rid of old stuff 
				//(but append /* so we don't delete the directory)
				safe_exec('rm -rf '. escapeshellarg(makeCorrectFile($configdir.'/*')));
			}
		}

		if(!isset($webserver))
		{
			if($settings['system']['webserver'] == "apache2")
			{
				if($settings['system']['mod_fcgid'] == 1)
				{
					$webserver = new apache_fcgid($db, $cronlog, $debugHandler, $idna_convert, $settings);
				}
				else
				{
					$webserver = new apache($db, $cronlog, $debugHandler, $idna_convert, $settings);
				}
			}
			elseif($settings['system']['webserver'] == "lighttpd")
			{
				if($settings['system']['mod_fcgid'] == 1)
				{
					$webserver = new lighttpd_fcgid($db, $cronlog, $debugHandler, $idna_convert, $settings);
				}
				else
				{
					$webserver = new lighttpd($db, $cronlog, $debugHandler, $idna_convert, $settings);
				}
			}
		}

		if(isset($webserver))
		{
			$webserver->createIpPort();
			$webserver->createVirtualHosts();
			$webserver->createFileDirOptions();
			$webserver->writeConfigs();
			$webserver->createOwnVhostStarter();
			$webserver->reload();
		}
		else
		{
			echo "Please check you Webserver settings\n";
		}
	}

	/**
	 * TYPE=2 MEANS TO CREATE A NEW HOME AND CHOWN
	 */
	elseif ($row['type'] == '2')
	{
		fwrite($debugHandler, '  cron_tasks: Task2 started - create new home' . "\n");
		$cronlog->logAction(CRON_ACTION, LOG_INFO, 'Task2 started - create new home');

		if(is_array($row['data']))
		{
			$cronlog->logAction(CRON_ACTION, LOG_NOTICE, 'Running: mkdir -p ' . escapeshellarg($settings['system']['documentroot_prefix'] . $row['data']['loginname'] . '/webalizer'));
			safe_exec('mkdir -p ' . escapeshellarg($settings['system']['documentroot_prefix'] . $row['data']['loginname'] . '/webalizer'));
			$cronlog->logAction(CRON_ACTION, LOG_NOTICE, 'Running: mkdir -p ' . escapeshellarg($settings['system']['documentroot_prefix'] . $row['data']['loginname'] . '/awstats'));
			safe_exec('mkdir -p ' . escapeshellarg($settings['system']['documentroot_prefix'] . $row['data']['loginname'] . '/awstats'));
			$cronlog->logAction(CRON_ACTION, LOG_NOTICE, 'Running: mkdir -p ' . escapeshellarg($settings['system']['vmail_homedir'] . $row['data']['loginname']));
			safe_exec('mkdir -p ' . escapeshellarg($settings['system']['vmail_homedir'] . $row['data']['loginname']));

			//check if admin of customer has added template for new customer directories
			$destdir = makeCorrectDir($settings['system']['documentroot_prefix'] . '/' . $row['data']['loginname']);
			storeDefaultIndex($row['data']['loginname'], $destdir, $cronlog, true);

			$cronlog->logAction(CRON_ACTION, LOG_NOTICE, 'Running: chown -R ' . (int)$row['data']['uid'] . ':' . (int)$row['data']['gid'] . ' ' . escapeshellarg($settings['system']['documentroot_prefix'] . $row['data']['loginname']));
			safe_exec('chown -R ' . (int)$row['data']['uid'] . ':' . (int)$row['data']['gid'] . ' ' . escapeshellarg($settings['system']['documentroot_prefix'] . $row['data']['loginname']));
			$cronlog->logAction(CRON_ACTION, LOG_NOTICE, 'Running: chown -R ' . (int)$settings['system']['vmail_uid'] . ':' . (int)$settings['system']['vmail_gid'] . ' ' . escapeshellarg($settings['system']['vmail_homedir'] . $row['data']['loginname']));
			safe_exec('chown -R ' . (int)$settings['system']['vmail_uid'] . ':' . (int)$settings['system']['vmail_gid'] . ' ' . escapeshellarg($settings['system']['vmail_homedir'] . $row['data']['loginname']));
		}
	}

	/**
	 * TYPE=3 MEANS TO DO NOTHING
	 */
	elseif ($row['type'] == '3')
	{
	}

	/**
	 * TYPE=4 MEANS THAT SOMETHING IN THE BIND CONFIG HAS CHANGED. REBUILD froxlor_bind.conf
	 */
	elseif ($row['type'] == '4')
	{
		if(!isset($nameserver))
		{
			$nameserver = new bind($db, $cronlog, $debugHandler, $settings);
		}

		if($settings['dkim']['use_dkim'] == '1')
		{
			$nameserver->writeDKIMconfigs();
		}

		$nameserver->writeConfigs();
	}

	/**
	 * TYPE=5 MEANS THAT A NEW FTP-ACCOUNT HAS BEEN CREATED, CREATE THE DIRECTORY
	 */
	elseif ($row['type'] == '5')
	{
		$cronlog->logAction(CRON_ACTION, LOG_INFO, 'Creating new FTP-home');
		$result_directories = $db->query('SELECT `f`.`homedir`, `f`.`uid`, `f`.`gid`, `c`.`documentroot` AS `customerroot` FROM `' . TABLE_FTP_USERS . '` `f` LEFT JOIN `' . TABLE_PANEL_CUSTOMERS . '` `c` USING (`customerid`) ');

		while($directory = $db->fetch_array($result_directories))
		{
			mkDirWithCorrectOwnership($directory['customerroot'], $directory['homedir'], $directory['uid'], $directory['gid']);
		}
	}

	/**
	 * TYPE=6 MEANS THAT A CUSTOMER HAS BEEN DELETED AND THAT WE HAVE TO REMOVE ITS FILES
	 */
	elseif ($row['type'] == '6')
	{
		fwrite($debugHandler, '  cron_tasks: Task6 started - deleting customer data' . "\n");
		$cronlog->logAction(CRON_ACTION, LOG_INFO, 'Task6 started - deleting customer data');

		if(is_array($row['data']))
		{
			if(isset($row['data']['loginname']))
			{
				/*
				 * remove homedir
				 */
				$homedir = makeCorrectDir($settings['system']['documentroot_prefix'] . '/' . $row['data']['loginname']);

				if($homedir != '/'
				&& $homedir != $settings['system']['documentroot_prefix']
				&& substr($homedir, 0, strlen($settings['system']['documentroot_prefix'])) == $settings['system']['documentroot_prefix'])
				{
					$cronlog->logAction(CRON_ACTION, LOG_NOTICE, 'Running: rm -rf ' . escapeshellarg($homedir));
					safe_exec('rm -rf '.escapeshellarg($homedir));
				}

				/*
				 * remove maildir
				 */
				$maildir = makeCorrectDir($settings['system']['vmail_homedir'] . '/' . $row['data']['loginname']);

				if($maildir != '/'
				&& $maildir != $settings['system']['vmail_homedir']
				&& substr($maildir, 0, strlen($settings['system']['vmail_homedir'])) == $settings['system']['vmail_homedir'])
				{
					$cronlog->logAction(CRON_ACTION, LOG_NOTICE, 'Running: rm -rf ' . escapeshellarg($maildir));
					safe_exec('rm -rf '.escapeshellarg($maildir));
				}

				/*
				 * see if we have some php-fcgid leftovers if used
				 * and remove them, #200
				 */
				if($settings['system']['mod_fcgid'] == 1)
				{
					// e.g. /var/www/php-fcgi-starter/web1/
					$configdir = makeCorrectDir($settings['system']['mod_fcgid_configdir'] . '/' . $row['data']['loginname'] . '/');
					
					if (is_dir($configdir)) 
					{
						$its = new RecursiveIteratorIterator(
							new RecursiveDirectoryIterator($configdir)
						);

						// iterate through all subdirs,
						// look for php-fcgi-starter files
						// and take immutable-flag away from them
						// so we can delete them :)
						foreach ($its as $fullFileName => $it ) 
						{
							if ($it->isFile() && $it->getFilename() == 'php-fcgi-starter') 
							{
								removeImmutable($its->getPathname());
							}
						}
						// now get rid of old stuff
						safe_exec('rm -rf '. escapeshellarg($configdir));
					}				
				}
			}
		}
	}

	/**
	 * TYPE=7 Customer deleted an email account and wants the data to be deleted on the filesystem
	 */
	elseif ($row['type'] == '7')
	{
		fwrite($debugHandler, '  cron_tasks: Task7 started - deleting customer e-mail data' . "\n");
		$cronlog->logAction(CRON_ACTION, LOG_INFO, 'Task7 started - deleting customer e-mail data');

		if(is_array($row['data']))
		{
			if(isset($row['data']['loginname'])
				&& isset($row['data']['email'])
			) {
				/*
				 * remove specific maildir
				 */
				$maildir = makeCorrectDir($settings['system']['vmail_homedir'] .'/'. $row['data']['loginname'] .'/'. $row['data']['email']);

				if($maildir != '/'
				&& $maildir != $settings['system']['vmail_homedir']
				&& substr($maildir, 0, strlen($settings['system']['vmail_homedir'])) == $settings['system']['vmail_homedir'])
				{
					$cronlog->logAction(CRON_ACTION, LOG_NOTICE, 'Running: rm -rf ' . escapeshellarg($maildir));
					safe_exec('rm -rf '.escapeshellarg($maildir));
				}
			}
		}
	}

	/**
	 * TYPE=8 Customer deleted a ftp account and wants the homedir to be deleted on the filesystem
	 * refs #293
	 */
	elseif ($row['type'] == '8')
	{
		fwrite($debugHandler, '  cron_tasks: Task8 started - deleting customer ftp homedir' . "\n");
		$cronlog->logAction(CRON_ACTION, LOG_INFO, 'Task8 started - deleting customer ftp homedir');

		if(is_array($row['data']))
		{
			if(isset($row['data']['loginname'])
				&& isset($row['data']['homedir'])
			) {
				/*
				 * remove specific homedir
				 */
				$ftphomedir = makeCorrectDir($row['data']['homedir']);
				$customerdocroot = makeCorrectDir($settings['system']['documentroot_prefix'].'/'.$row['data']['loginname'].'/');

				if($ftphomedir != '/'
				&& $ftphomedir != $settings['system']['documentroot_prefix']
				&& $ftphomedir != $customerdocroot
				) {
					$cronlog->logAction(CRON_ACTION, LOG_NOTICE, 'Running: rm -rf ' . escapeshellarg($ftphomedir));
					safe_exec('rm -rf '.escapeshellarg($ftphomedir));
				}
			}
		}
	}
}

if($db->num_rows($result_tasks) != 0)
{
	$where = array();
	foreach($resultIDs as $id)
	{
		$where[] = '`id`=\'' . (int)$id . '\'';
	}

	$where = implode($where, ' OR ');
	$db->query('DELETE FROM `' . TABLE_PANEL_TASKS . '` WHERE ' . $where);
	unset($resultIDs);
	unset($where);
}

$db->query('UPDATE `' . TABLE_PANEL_SETTINGS . '` SET `value` = UNIX_TIMESTAMP() WHERE `settinggroup` = \'system\'   AND `varname` = \'last_tasks_run\' ');

?>
