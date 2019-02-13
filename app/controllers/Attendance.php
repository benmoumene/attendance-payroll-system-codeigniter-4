<?php
/**
 * Attendence Controller
 */
class Attendance extends Controller   
{
	
	public function __construct()
	{
		// auth check
		if (!isset($_SESSION['user_id'])) {
			header("Location: /admin/login");       
		}
		
		$this->attendenceModel = $this->model('AttendanceModel');   
		$this->frontModel = $this->model('FrontModel');     
	} 

	public function index()
	{
		$attendances = $this->attendenceModel->attendances(); 
		$employees = $this->attendenceModel->employees();  

		$data = [
			'attendances' => $attendances,
			'employees' =>  $employees,
			'title' => 'Attendance'
		];
		$this->view('backend/attendance/index', $data);   
	}

	public function create() 
	{	
		$employees = $this->attendenceModel->employees();     

		if ($_SERVER['REQUEST_METHOD'] == 'POST') {   
			// sanitize post data
			$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING); 
			
			// init data 
			$data = [
				'title' => 'Attendance Create',
				'employees' => $employees,
				'employee_id' => trim($_POST['employee_id']),     
				'created_at'  => $_POST['created_at'], 
				'in_time'     => $_POST['in_time'],   
				'status'	  => $_POST['status'],       
				'employee_error' => '', 
				'date_error' => '',
				'intime_error' => '',
				'status_error' => ''  
			]; 

			// validation
			if (empty($data['employee_id'])) {   
				$data['employee_error'] = 'Employee ID is required.'; 
			} 

			if (empty($data['created_at'])) { 
				$data['date_error'] = 'Date is required.';
			} else {
				$data['created_at'] = date('Y-m-d', strtotime($_POST['created_at']));   
			} 

			if (empty($data['in_time'])) {
				$data['intime_error'] = 'In Time required.';
			} else {
				$data['in_time'] = date('H:i:s', strtotime($_POST['in_time']));  
			}  

			if (empty($data['status'])) {
				$data['status_error'] = 'Status is required.';  
			}  elseif($data['status'] !=1 && $data['status'] != 0) {
				$data['status_error'] = 'Opps!, Invalid Formate.';   
			}

			// Makes sure errors are empty 
			if ( empty($data['employee_error']) && empty($data['date_error']) && empty($data['intime_error']) && empty($data['status_error']) ) {  

				// prcess  
				if($this->attendenceModel->create($data)) { // receive true/false 
					flash('success', 'Attedance has created', 'alert alert-success alert-dismissible');        
					redirect('attendance/index');         
				} else { 
					die('Something wend wrong'); 
					$this->view('backend/attendance/create', $data);         
				}
				
			} else { // load view with errors
				$this->view('backend/attendance/create', $data);       
			} 

		}  else { // if get request 
			$data = [
				'title' => 'Attendance Create',
				'employees' => $employees,
				'employee_error' => '', 
				'date_error' => '',
				'intime_error' => '',
				'status_error' => ''  
			];
			$this->view('backend/attendance/create', $data);     
		} 
	}

	public function edit($id) 
	{	  
		$attendance = $this->attendenceModel->findById($id);           

		if ($_SERVER['REQUEST_METHOD'] == 'POST') {   
			// sanitize post data
			$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING); 
			
			// init data 
			$data = [
				'title' => 'Attendance Create',
				'attendance' => $attendance,        
				'created_at'  => $_POST['created_at'], 
				'in_time'     => $_POST['in_time'], 
				'out_time'    => $_POST['out_time'],   
				'status'	  => 1,      
				'date_error' => '',
				'intime_error' => '',
				'outtime_error' => '' 
			]; 

			// validation 
			if (empty($data['created_at'])) { 
				$data['date_error'] = 'Date is required.';
			} else {
				$data['created_at'] = date('Y-m-d', strtotime($_POST['created_at']));   
			} 

			if (empty($data['in_time'])) {
				$data['intime_error'] = 'In Time required.';
			} else {
				$data['in_time'] = date('H:i:s', strtotime($_POST['in_time']));  
			}  

			if (empty($data['out_time'])) {
				$data['outtime_error'] = 'Out Time is required.'; 
			}  else {
				$data['out_time'] = date('H:i:s', strtotime($_POST['out_time']));  
			} 

			// Makes sure errors are empty 
			if ( empty($data['date_error']) && empty($data['intime_error']) && empty($data['outtime_error']) ) {  
				// prcess  
				if($this->attendenceModel->update($data, $id)) {   

					// -------------------- working hours calculation ---------------
					$time_in = ''; 
					$time_out = ''; 
					$attendanceData = $this->frontModel->attendanceById($id);  
					$in_time_from_attendance = $attendanceData->in_time;
					$out_time_from_attendance = $attendanceData->out_time;  
					$employeeId = $attendanceData->employee_id;     
					
					// employee in_time, out_time 
					$employeeData = $this->frontModel->employeeById($employeeId);     
					$employee_start_time  = $employeeData->in_time; 
					$employee_end_time = $employeeData->out_time;    
					
					// if employee starting tiem is grater than from attendance time
					// start before from schedule  
					if($employee_start_time > $in_time_from_attendance) {  
						$time_in = $employee_start_time;  
					} else {
						$time_in = $in_time_from_attendance;
					}
					// if employee ending tiem is grater than from attendance stop time  
					// stop before schedule 
					if($employee_end_time < $out_time_from_attendance){
						 $time_out = $employee_end_time;    
					} else {
						$time_out = $out_time_from_attendance;    
					}
					$time_in = new DateTime($time_in);
					$time_in->format('H:i:s');
					$time_out = new DateTime($time_out);
					$time_out->format('H:i:s');  
					$interval = $time_in->diff($time_out);  
					$hrs = $interval->format('%h');      
					$mins = $interval->format('%i'); 
					$seconds = $interval->format('%s');  
					// $mins = $mins/60; 
					$workingTime = $hrs.':'.$mins.':'.$seconds;      

					$this->frontModel->employeeWorkingHours($workingTime, $id);     
					// -------------------- end working hours calculation ---------------
					flash('success', 'Attedance has updated');             
					redirect('attendance/index');         
				} else { 
					die('Something wend wrong'); 
					$this->view('backend/attendance/edit', $data);         
				}
				
			} else { // load view with errors
				$this->view('backend/attendance/edit', $data);        
			} 

		}  else { // if get request 
			$data = [
				'title' => 'Attendance Create',
				'attendance' => $attendance,  
				'date_error' => '',
				'intime_error' => '',
				'outtime_error' => ''  
			];
			$this->view('backend/attendance/edit', $data);       
		} 
	}

	public function delete($id)
	{
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			if ($this->attendenceModel->destroy($id)) {
				flash('success', 'Attendance has been removed'); 
				redirect('attendance/index');      
			} else {
				die('Something went wrong');
			}
		} else {
			redirect('attendance/index');    
		}
	
	} 

	
}