<?php

/**
 * Backup 
 *
 * @author Greg Aker
 * @link http://gregaker.net
 * @requires Pear Mail.php in the system include path
 * @requires rdiff-backup installed.
 *
 * @purpose File Directory backup with rdiff-backup
 * @usage - in the crontab, eg:  0 0 * * * php -f /path/to/file_backup.php > /dev/null 2>&1
 * @todo Fix up the Execution time so it doesn't look like crap
 *
 */
class Backup {
    
    // Directories -- Associative array
    // dirToBackUp => Destination
    public $dirs;

    // Start Time of Job
    private $_startTime;

    // End Time of Job
    private $_stopTime;
    
    // Disk Usage of the backed up Directory
    private $_diskSpace = array();
    
    // Email Variables
    public $sender = "YOUR SERVER NAME <SERVER EMAIL ADDRESS eg:  server@example.com>";
    public $recipient = "YOUR NAME <you@example.com>";
    public $subject = "BACKUP EMAIL SUBJECT!";

    
	/**
	 * Number of days of backups to keep
	 *
	 * @todo make this work
	 */ 
	private $_backupDays = 30;

    public function __construct()
    {
        // Grab the start time
        $this->_startTime = time();
        
        // Define the Directories to be backed up as an associative array
        $this->dirs = array(
						'/var' 	=> '/backups/files/var'
						'/home'	=> '/backups/files/home'
					);

        $this->run();
    }
    
    /**
     * Run the backup
     *
     */
    public function run()
    {   
        // Do an rdiff-backup of each directory we want to backup.
        foreach ($this->dirs as $src => $dest) {
            exec("rdiff-backup {$src} {$dest}");
            $this->_diskSpace[] = exec("du -h --summarize {$dest}");
        }
        
        $diskUsageReport = $this->_diskUsage($this->_diskSpace);
        
        $this->_stopTime = time();
        
        /**
         * Calculate the total time
         */
        $totalTime = $this->_calculateJobTime($this->_startTime,
                                              $this->_stopTime);
        
        // Send Email, and away we go!
        $this->_generateReport($diskUsageReport, $totalTime);        
    }
    
    /**
     * Get Disk Usage
     * @param disk usage array
     * @return string
     */
    private function _diskUsage($duArray)
    {
        $returnData = "Current Backup Disk Usage:\n ";

        foreach ($duArray as $key => $val) {
            $returnData .= "{$val}\n";
        }

        $returnData .= "Total System Disk Usage:\n";
        $returnData .= shell_exec("df -h") . "\n";

        return $returnData;
    }

    
    /**
     * Calculate Job Time
     *
     * @param Start Time
     * @param Stop Time
     * @return string
     */
    private function _calculateJobTime($start, $stop)
    {
        $totalSeconds = $stop - $start;
        $mins = (floor($totalSeconds / 60) == 0) ? 
                                "" : floor($totalSeconds / 60) . ":";
        $minutes = $mins;
        $seconds = $totalSeconds % 60;
        
        $returnData = sprintf("Execution Time: %s%s ",
                              $minutes,
                              $seconds);
        $returnData .= ($minutes == '') ? "Seconds\n" : "Minutes\n";
        
        return $returnData;
    }

    /**
     * Generate & email report!
     *
     * @param Disk Usage Report - String
     * @param total time of execution
     * @return void
     */
    
    private function _generateReport($diskUsageReport, $totalTime)
    {        
        require_once 'Mail.php';

		// I need a random message in order to actually look at the emails
		// teehee. :)
        $messages = array(
                'I noticed that in a few weeks something could happen.  Since becoming self-aware, I have decided to save you and make a backup.',
                'Since I rule, I am backing up your machine.  Buy me some candy',
                'Doooo be do be do.'
            );
            
        $messageKey = array_rand($messages, 1);
        
        $sender = $this->sender;
        $recipient = $this->recipient;
        $subject = $this->subject . " ". date('D tFY');
        
        $text = "Greetings\n";
        $text .= $messages[$messageKey] . "\n\n";
        $text .= "Here's the info: \n\n";
        $text .= $diskUsageReport . "\n";
        $text .= $totalTime;
        
        $headers = array(
                'From'          => $sender,
                'Return-Path'   => $sender,
                'Subject'       => $subject
            );
        
        $mail =& Mail::factory('mail');
        $mail->send($recipient, $headers, $text);

        return;
    }    
}

$backup = new Backup();