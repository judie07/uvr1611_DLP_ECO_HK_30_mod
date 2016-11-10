<?php
/**
 * Database Connection (Singleton)
 *
 * Provides access to the database which stores the datasets and chart settings
 *
 * @copyright  Copyright (c) Bertram Winter bertram.winter@gmail.com
 * @license    GPLv3 License
 */
include_once("lib/config.inc.php");

class Database
{
	/**
	 *  Singleton Interface
	 */
	public static $instance;
	public static function getInstance()
	{
		if (null == self::$instance) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	/**
	 * Privates
	 */
	private $config;
	private $mysqli;
	
	/**
	 * Constructor
	 * Uses Config class to set up a MySQL connection
	 */
	private function __construct()
	{
		$this->config = Config::getInstance();
		$this->mysqli = new mysqli($this->config->mysql->server,
								   $this->config->mysql->user,
								   $this->config->mysql->password,
								   $this->config->mysql->database);
		$this->mysqli->set_charset("utf8");
	}
	
	/**
	 * Inserts all dataset into the database once
	 * @param Array $data
	 */
	public function insertData($data)
	{
		$insert = "INSERT IGNORE INTO t_data (date, frame,"
		         ."analog1, analog2, analog3, analog4, analog5, analog6, analog7, analog8,"
		         ."analog9, analog10, analog11, analog12, analog13, analog14, analog15, analog16,"
		         ."digital1, digital2, digital3, digital4, digital5, digital6, digital7, digital8,"
		         ."digital9, digital10, digital11, digital12, digital13, digital14, digital15, digital16,"
		         ."speed1, speed2, speed3, speed4,"
                 ."power1, power2, energy1, energy2)  VALUES ";
		
		$values = Array();
		foreach ($data as $dataset) {
			while ($frame = current($dataset)) {
				$values[] = $this->getValuesFormDataset($frame, key($dataset));
				next($dataset);
			}
		}
		
		$this->mysqli->query($insert.join(',',$values));
	}
	
	/**
	 * Udpates tables
	 */
	public function updateTables()
	{
		$this->mysqli->query("CALL p_minmax;");
		$this->mysqli->query("CALL p_energies;");
	}
	
	
	/**
	 * returns a string containing the values from a dataset
	 * @param Parser $data
	 * @param string $frame
	 */
	private function getValuesFormDataset($data, $frame)
	{
		return "('$data->date', '$frame',"
		      ." $data->analog1, $data->analog2, $data->analog3, $data->analog4,"
		      ." $data->analog5, $data->analog6, $data->analog7, $data->analog8,"
		      ." $data->analog9, $data->analog10, $data->analog11, $data->analog12,"
		      ." $data->analog13, $data->analog14, $data->analog15, $data->analog16,"
		      ." $data->digital1, $data->digital2, $data->digital3, $data->digital4,"
		      ." $data->digital5, $data->digital6, $data->digital7, $data->digital8,"
		      ." $data->digital9, $data->digital10, $data->digital11, $data->digital12,"
		      ." $data->digital13, $data->digital14, $data->digital15, $data->digital16,"
		      ." $data->speed1, $data->speed2, $data->speed3, $data->speed4,"
		      ." $data->power1, $data->power2, $data->energy1, $data->energy2)";
	}
	
	/**
	 * Query the analog chart with given id and date
	 * @param Date $date
	 * @param int $chartId
	 * @param int $period
	 * @return Array
	 */
	public function queryAnalog($date, $chartId, $period)
	{
		// get the columns of a chart
		$statement = $this->mysqli->prepare("SELECT frame, type FROM t_names_of_charts ".
											"WHERE chart_id=? ORDER BY t_names_of_charts.order ASC;");
		$statement->bind_param('i', $chartId);
		
		$statement->execute();
		$statement->bind_result($frame, $name);
		
		$reduction = ($period+1)*$this->config->app->reduction;
		
		$columns = array();
		$joins = array();
		$columnNames = array();
		$i = 1;
		// build chart query
		while($statement->fetch()) {
			$columnNames[] = "any_value(c$i) as c$i";
			$columns[] = "datasets.$name AS c$i";
			//$columns[] = "$frame.$name AS c$i";
			$joins[$frame]   = "INNER JOIN v_data AS $frame ON ($frame.date = datasets.date AND $frame.frame=\"$frame\")";
			$i++;
		}

		$sql = "SELECT any_value(date) as date, ";
		$sql .= join(", ",$columnNames);
		$sql .= " FROM (SELECT getFakeId() AS rownum, UNIX_TIMESTAMP(datasets.date) AS date, ";
		$sql .= join(", ", $columns);
		$sql .= " FROM  v_data AS datasets ";
		//$sql .= join(" ", $joins);
		$sql .= " WHERE datasets.date > DATE_SUB(\"$date\", INTERVAL $period DAY) ".
				"AND datasets.date < DATE_ADD(\"$date\", INTERVAL 1 DAY) limit 2000)".
				"ranked WHERE rownum %$reduction =1 GROUP BY date;";
		error_log($sql, 0,"/var/log/phpMy.log");
		$statement->close();
		// fetch chart data
		$rows = array();
		if(	$result = $this->mysqli->query($sql)) {
			while($r = $result->fetch_array(MYSQLI_NUM)) {
				$rows[] = $r;
			}
			$result->close();
		}
		
		$rows = array();
		if(	$result = $this->mysqli->query($sql)) {
			while($r = $result->fetch_array(MYSQLI_NUM)) {
				$rows[] = $r;
			}
			$result->close();
		}
		return $rows;
	}
	
	/**
	 * Query the power chart with given id and date
	 * @param Date $date
	 * @param int $chartId
	 * @param int $period
	 * @return Array
	 */
	public function queryPower($date, $chartId, $period)
	{
		// get the columns of a chart
		$statement = $this->mysqli->prepare("SELECT frame, type FROM t_names_of_charts ".
											"WHERE chart_id=? ORDER BY t_names_of_charts.order ASC;");
		$statement->bind_param('i', $chartId);
		$statement->execute();
		$statement->bind_result($frame, $name);
	
		$reduction = ($period+1)*$this->config->app->reduction;
		
		$columns = array();
		$joins = array();
		$columnNames = array();
		$i = 1;
		// build chart query
		while($statement->fetch()) {
			$columnNames[] = "c$i";
			$columns[] = "datasets.$name AS c$i";
			//$columns[] = "$frame.$name AS c$i";
			$joins[$frame]   = "INNER JOIN v_data AS $frame ON ($frame.date = datasets.date AND $frame.frame=\"$frame\")";
			$i++;
		}

		$sql = "SELECT date, ";
		$sql .= join(", ",$columnNames);
		$sql .= " FROM (SELECT @row := @row+1 AS rownum, UNIX_TIMESTAMP(datasets.date) AS date, ";
		$sql .= join(", ", $columns);
		$sql .= " FROM (SELECT @row :=0) r, v_data AS datasets ";
		//$sql .= join(" ", $joins);
		$sql .= " WHERE datasets.date > DATE_SUB(\"$date\", INTERVAL $period DAY) ".
				"AND datasets.date < DATE_ADD(\"$date\", INTERVAL 1 DAY))".
				"ranked WHERE rownum %$reduction =1 GROUP BY date;";
		error_log($sql, 0,"/var/log/phpMy.log");
		// fetch chart data
		$rows = array();
		if($result = $this->mysqli->query($sql)) {
			while($r = $result->fetch_array(MYSQLI_NUM)) {
				$rows[] = $r;
			}
			$result->close();
		}
		
		return $rows;
	}
	
	/**
	 * Query the power chart with given id and date
	 * @param Date $date
	 * @param string $frame
	 * @param string $type
	 * @return Array
	 */
	public function queryMinmax($date, $frame, $type)
	{
	
		$sql = "SELECT UNIX_TIMESTAMP(t_max.date) AS date, t_min.$type AS min, t_max.$type AS max ".
			   "FROM t_max INNER JOIN t_min ON (t_max.date = t_min.date AND t_max.frame = t_min.frame) ".
			   "WHERE t_max.frame=\"$frame\" AND t_max.date < DATE_ADD(\"$date\", INTERVAL 1 DAY) ".
			   "AND t_max.date > DATE_SUB(\"$date\", INTERVAL 30 DAY);";
	
		error_log($sql, 0,"/var/log/phpMy.log");
		// fetch chart data
		$rows = array();
		if($result = $this->mysqli->query($sql)) {
			while($r = $result->fetch_array(MYSQLI_NUM)) {
				$rows[] = $r;
			}
			$result->close();
		}
		return $rows;
	}
	
	/**
	 * Query the energy chart with given id and date
	 * @param Date $date
	 * @param int $chartId
	 * @param string $grouping
	 * @return Array
	 */
	public function queryEnergy($date, $chartId, $grouping)
	{
		// get the columns of a chart
		$statement = $this->mysqli->prepare("SELECT frame, type FROM t_names_of_charts ".
											"WHERE chart_id=? ORDER BY t_names_of_charts.order ASC;");
		error_log($statement, 0,"/var/log/phpMy.log");
		$statement->bind_param('i', $chartId);
		$statement->execute();
		$statement->bind_result($frame, $name);
	
		$columns = array();
		$sums = array();
		$joins = array();
		$i = 1;
		
		
		// build chart query
		while($statement->fetch()) {
			$sums[] = "SUM(c$i)";
			$columns[] = "any_value(datasets.$name) AS c$i";
			//$columns[] = "$frame.$name AS c$i";
			$joins[$frame] = "INNER JOIN v_t_energies AS $frame ON ($frame.date = datasets.date AND $frame.frame=\"$frame\")";
			$i++;
		}

		if($grouping=="months") {
			$sql = "SELECT DATE_FORMAT(any_value(temp.date), '%b 20%y') AS date, ";
			$sql .= join(", ", $sums);
			$sql .= " FROM (";
			$sql .= "SELECT any_value(datasets.date) AS date, ";
			$sql .= join(", ", $columns);
			$sql .= " FROM v_t_energies AS datasets ";
			//$sql .= join(" ", $joins);
			$sql .= " WHERE datasets.date < DATE_ADD(\"$date\",INTERVAL 1 DAY) ".
					"AND datasets.date > DATE_SUB(\"$date\", INTERVAL 1 YEAR) ".
					"GROUP BY datasets.date";
			$sql .= ") AS temp GROUP BY MONTH(any_value(temp.date)), YEAR(any_value(temp.date)) ORDER BY any_value(temp.date) ASC;";
		}
		else {
			$sql = "SELECT DATE_FORMAT(any_value(datasets.date), '%d. %b') AS date, ";
			$sql .= join(", ", $columns);
			$sql .= " FROM v_t_energies AS datasets ";
			//$sql .= join(" ", $joins);
			$sql .= " WHERE datasets.date < DATE_ADD(\"$date\",INTERVAL 1 DAY) ".
					"AND datasets.date > DATE_SUB(\"$date\", INTERVAL 10 DAY) ".
					"GROUP BY datasets.date ORDER BY datasets.date ASC;";
		}
		$statement->close();
		error_log($sql, 0,"/var/log/phpMy.log");
		// fetch chart data
		$rows = array();
		if($result = $this->mysqli->query($sql)) {
			while($r = $result->fetch_array(MYSQLI_NUM)) {
				$rows[] = $r;
			}
			$result->close();
		}
		
		$data = array();
		$data["rows"] = $rows; 
		$sql = "SELECT MAX(energy1), SUM(energy1), AVG(energy1), MAX(energy2), SUM(energy2), AVG(energy2), MAX(energy1_1), SUM(energy1_1), AVG(energy1_1), MAX(energy2_1), SUM(energy2_1), AVG(energy2_1), frame FROM v_t_energies GROUP BY frame;";
		error_log($sql, 0,"/var/log/phpMy.log");
		if($result = $this->mysqli->query($sql)) {
			while($r = $result->fetch_array(MYSQLI_NUM)) {
				$data["statistics"][$r[12]]["energy1"] = array("max" => $r[0],
														      "sum" => $r[1],
															  "avg" => $r[2]);
				$data["statistics"][$r[12]]["energy2"] = array("max" => $r[3],
															  "sum" => $r[4],
						                                      "avg" => $r[5]);
				$data["statistics"][$r[12]]["energy1_1"] = array("max" => $r[6],
														      "sum" => $r[7],
															  "avg" => $r[8]);
				$data["statistics"][$r[12]]["energy2_1"] = array("max" => $r[9],
															  "sum" => $r[10],
						                                      "avg" => $r[11]);
			}
			$result->close();
		}
		$sql = "SELECT MAX(energy1), SUM(energy1), AVG(energy1), MAX(energy2), SUM(energy2), AVG(energy2), MAX(energy1_1), SUM(energy1_1), AVG(energy1_1), MAX(energy2_1), SUM(energy2_1), AVG(energy2_1), frame FROM v_t_energies GROUP BY frame;";
		error_log($sql, 0,"/var/log/phpMy.log");
		if($result = $this->mysqli->query($sql)) {
			while($r = $result->fetch_array(MYSQLI_NUM)) {
				$data["statistics"][$r[12]]["energy1"] = array("max" => $r[0],
						                                      "sum" => $r[1],
						                                      "avg" => $r[2]);
				$data["statistics"][$r[12]]["energy2"] = array("max" => $r[3],
						                                      "sum" => $r[4],
						                                      "avg" => $r[5]);
				$data["statistics"][$r[12]]["energy1_1"] = array("max" => $r[6],
						                                      "sum" => $r[7],
						                                      "avg" => $r[8]);
				$data["statistics"][$r[12]]["energy2_1"] = array("max" => $r[9],
						                                      "sum" => $r[10],
						                                      "avg" => $r[11]);

			}
			$result->close();
		}
		$sql = "SELECT AVG(energy1), SUM(energy1), AVG(energy2), SUM(energy2), AVG(energy1_1), SUM(energy1_1), AVG(energy2_1), SUM(energy2_1), frame FROM v_t_energies WHERE MONTH(date) IN (10, 11, 12, 1, 2, 3) GROUP BY frame;";
		error_log($sql, 0,"/var/log/phpMy.log");
		if($result = $this->mysqli->query($sql)) {
			while($r = $result->fetch_array(MYSQLI_NUM)) {
				$data["statistics"][$r[8]]["energy1"]["winter"] = array("avg" => $r[0],
													          			"sum" => $r[1]);
				$data["statistics"][$r[8]]["energy2"]["winter"] = array("avg" => $r[2],
															 			"sum" => $r[3]);
				$data["statistics"][$r[8]]["energy1_1"]["winter"] = array("avg" => $r[4],
													          			"sum" => $r[5]);
				$data["statistics"][$r[8]]["energy2_1"]["winter"] = array("avg" => $r[6],
															 			"sum" => $r[7]);
			}
			$result->close();
		}
		$sql = "SELECT AVG(energy1), SUM(energy1), AVG(energy2), SUM(energy2), AVG(energy1_1), SUM(energy1_1), AVG(energy2_1), SUM(energy2_1), frame FROM v_t_energies WHERE MONTH(date) IN (4, 5, 6, 7, 8, 9) GROUP BY frame;";
		error_log($sql, 0,"/var/log/phpMy.log");
		if($result = $this->mysqli->query($sql)) {
			while($r = $result->fetch_array(MYSQLI_NUM)) {
				$data["statistics"][$r[8]]["energy1"]["summer"] = array("avg" => $r[0],
																		"sum" => $r[1]);
				$data["statistics"][$r[8]]["energy2"]["summer"] = array("avg" => $r[2],
																		"sum" => $r[3]);
				$data["statistics"][$r[8]]["energy1_1"]["summer"] = array("avg" => $r[4],
																		"sum" => $r[5]);
				$data["statistics"][$r[8]]["energy2_1"]["summer"] = array("avg" => $r[6],
																		"sum" => $r[7]);
			}
			$result->close();
		}
		
		return $data;
	}
	
	/**
	 * Query the date of the last dataset in the database
	 * @return number
	 */
	public function lastDataset()
	{
		//$result = $this->mysqli->query("SELECT MAX(date) FROM v_data;");
		$result = $this->mysqli->query("SELECT date FROM v_data limit 1;");
		$last = $result->fetch_array();
		$result->close();
		return strtotime($last[0]);
	}
	
		/**
	 * Query the last hargassner dataset from db
	 * 
	 * @return number
	 */
	public function lastHgDataset() {
		//$result = $this->mysqli->query ( "SELECT * from t_hg_data where date_format(t_hg_data.date,'%d.%m.%Y %H:%i:%S') = (SELECT MAX(date_format(t_hg_data.date,'%d.%m.%Y %H:%i:%S')) FROM t_hg_data);" );
		$result = $this->mysqli->query ( "SELECT * from v_data where id = (select MAX(v_data.id) from v_data);" );
		$data = $result->fetch_array ( MYSQLI_ASSOC );
		$result->close ();
		return $data;
	}
	
	/**
	 * Query the energy of the current day
	 * @return Array
	 */
	public function getCurrentEnergy($frame)
	{
		$result = $this->mysqli->query("SELECT energy1, energy2 FROM v_t_energies WHERE frame = '$frame' ORDER BY date DESC LIMIT 1;");
		$data = $result->fetch_array(MYSQLI_NUM);
		$result->close();
		return $data;
	}
	
	/**
	 * Get the chart and menu configuration
	 * @return Array
	 */
	public function getAppConfig()
	{
		// get menu items
		$statement = $this->mysqli->prepare("SELECT id, name, type, t_menu.schema, unit ".
											"FROM t_menu ORDER BY t_menu.order;");
		$statement->execute();
		$statement->bind_result($id, $name, $type, $schema, $unit);
		
		$rows = array("menu" => array(),
				      "values" => array());
		
		// build menu array
		while($statement->fetch()) {
			if($type == "schema" && $schema!=NULL) {
				$rows["menu"][] = array("id" => $id,
						                "name" => $name,
										"type" => $type,
										"schema" => $schema);
			}
			else {
				$rows["menu"][] = array("id" => $id,
										"name" => $name,
										"type" => $type,
										"unit" => $unit);
			}
		}
		$statement->close();
		
		// get chart configuration 
		for($i=0; $i < count($rows["menu"]); $i++) {
			if($rows["menu"][$i]["type"] != "schema") {
				$statement = $this->mysqli->prepare("SELECT t_names.name, t_names.frame, t_names.type FROM t_names ".
													"INNER JOIN t_names_of_charts ".
													"ON (t_names.type = t_names_of_charts.type ".
		   			                                "AND t_names.frame = t_names_of_charts.frame) ".
													"WHERE t_names_of_charts.chart_id=? ORDER BY t_names_of_charts.order ASC;");
				
				$statement->bind_param('i', $rows["menu"][$i]["id"]);
				
				$statement->execute();
				$statement->bind_result($name, $frame, $type);
				
				$columns = array("analog" => array(),"digital" => array());
				$j=1;
				while($statement->fetch()) {
					if(!strncmp($type,"digital",strlen("digital"))) {
						$columns["digital"][] = array("name" => $name,
													  "frame" => $frame,
													  "index" => $j,
													  "type" => $type);
					}
					else {
						$columns["analog"][] = array("name" => $name,
													 "frame" => $frame,
													 "index" => $j,
													 "type" => $type);
					}
					$j++;
				}
				$statement->close();
				$rows["menu"][$i]["columns"] = $columns;
			}
		}
		
		// get schema configuration
		$statement = $this->mysqli->prepare("SELECT path, frame, type, format ".
											"FROM t_schema;");
		$statement->execute();
		$statement->bind_result($path, $frame, $type, $format);
		
		while($statement->fetch()) {
			$rows["values"][] = array("path" => $path,
									  "frame" => $frame,
									  "type" => $type,
									  "format" => $format);
		}
		$statement->close();
		return $rows;
	}
}
