<?php
class Connections extends MY_Controller
{
    function __construct()
    {
        parent::__construct();

		$this->load->config('facebook');
		   		
		$this->check_connection = $this->connections_model->check_connection_user($this->session->userdata('user_id'), 'facebook');
	}	
		
	function index()
	{
		if ($this->data['settings']['facebook']['enabled'] != 'TRUE') redirect(base_url(), 'refresh');

		$this->load->library('facebook');

		$me 				= NULL;
		$album				= NULL;
		$profile_picture	= NULL;
		// IF USER IS LOGGED IN
		if ($this->social_auth->logged_in())
		{
			// IS THIS FACECBOOK ACCOUNT ALREADY CONNECTED			
			$check_connection = $this->connections_model->check_connection_user($this->session->userdata('user_id'), "facebook");
			// YES
			if ($check_connection)
			{							
				// ALREADY EXISTS
				$this->session->set_flashdata('message', "You already have a Facebook account connected");
				redirect('settings/connections', 'refresh');
			}
			// DOES NOT EXIST
			else
			{
				// NO TOKENS IN URL query string GO TO FACEBOOK
				if (!isset($_GET['session'])) redirect($this->facebook->getLoginUrl());
				// MAKE OBJECT OF session
				$url_vars = json_decode($_GET['session']);			
				// HAS TOKENS PROCESS EM
				if (isset($url_vars->access_token) && isset($url_vars->secret))
				{
					// SET FACEBOOK SESSION
					$this->facebook->getSession();
					// USER DETAILS
					$facebook_user = $this->facebook->api('/me');
					// ADD USER
					$check_user_connection = $this->connections_model->check_connection_user_id($facebook_user['id'], "facebook");
					// SUCCESS ADDED
					if($check_user_connection)
					{
					 	$this->session->set_flashdata('message', "This Facebook account is already connected to another user");
					 	redirect('settings/connections', 'refresh');
					}
					else
					{
						$connection = $this->connections_model->add_connection($this->session->userdata('user_id'), 'facebook', $url_vars->access_token, $url_vars->secret, $facebook_user['id'], $facebook_user['name'], $_GET['session']);
						// GO GET EXTENDED PERMISSIONS
						if ($this->config->item('facebook_extended'))
						{
							redirect('https://graph.facebook.com/oauth/authorize?client_id='.$this->config->item('facebook_app_id').'&redirect_uri='.base_url().'connections/facebook_extended/'.$connection->connection_id.'&scope='.$this->config->item('facebook_extended_options'));
						}
						$this->session->set_flashdata('message', "Facebook account connected");
					 	redirect('settings/connections', 'refresh');	
					}
				}
			}
	    }	    
		// USER NOT LOGGED IN
		else
		{
			// NO TOKENS IN URL query string GO TO FACEBOOK
			if (!isset($_GET['session'])) redirect($this->facebook->getLoginUrl());
			// MAKE OBJECT OF session
			$url_vars = json_decode($_GET['session']);
			// HAS TOKENS PROCESS
			if (isset($url_vars->access_token) && isset($url_vars->secret))
			{
				// SET FACEBOOK SESSION
				$this->facebook->getSession();
				// USER DETAILS
				$facebook_user = $this->facebook->api('/me');			
				// IS FACEBOOK ACCOUNT ALREADY CONNECTED	
				$check_user_connection = $this->connections_model->check_connection_user_id($facebook_user['id'], "facebook");
				// CHECK CONNECTION THEN ATTEMPT LOGIN
				if ($check_user_connection)
				{					
					// LOG USER IN WITH SOCIAL
		        	if ($this->social_auth->social_login('facebook', $check_user_connection->user_id, $check_user_connection->token_one, $check_user_connection->token_two, $check_user_connection->connection_password)) 
		        	{ 
			        	$this->session->set_flashdata('message', "Login with Facebook Success");
			        	redirect(base_url().'home', 'refresh');
			        }
			     	// ERROR LOGGING IN   
			        else 
			        { 
			        	$this->session->set_flashdata('message', "Login with Facebook Did Not Work");
			        	redirect("login", 'refresh');
			        }
				}
				// FACEBOOK ACCOUNT NOT CONNECTED TO ANYONE CREATE ACCOUNT
				else
				{
					// GET EXTENDED PERMISSIONS adds connection with empty user_id so hopefully we get email address upon return
					if ($this->config->item('facebook_extended'))
					{
						$connection = $this->connections_model->add_connection(0, 'facebook', $url_vars->access_token, $url_vars->secret, $facebook_user['id'], $facebook_user['name'], $_GET['session']);
						redirect('https://graph.facebook.com/oauth/authorize?client_id='.$this->config->item('facebook_app_id').'&redirect_uri='.base_url().'connections/facebook_extended/'.$connection->connection_id.'&scope='.$this->config->item('facebook_extended_options'));
					}
					// ELSE CREATE USER ACCOUNT AND CONNECTION
					$username	= url_username($facebook_user['name'], 'none', true);				
					// CONVERTS FACEBOOK TIMEZONE TO STANDARD					
					foreach(timezones() as $key => $zone)
					{
						if ($facebook_user['timezone'] === $zone) $time_zone = $key;						
					}	
		        	$utc_offset	= $facebook_user['timezone'] * 60 * 60;
		        						
		        	$additional_data = array(
	    				'name' 		 	=> $facebook_user['name'],
						'location'	 	=> $facebook_user['location']['name'],
						'bio' 		 	=> '',
						'url'	 	 	=> $facebook_user['link'],
						'image'		 	=> '',
						'home_base'		=> 'facebook',
						'language'		=> $this->config->item('languages_default'),
						'time_zone'		=> $time_zone,
						'geo_enabled'	=> 0,
						'utc_offset' 	=> $utc_offset
    				);

		        	// Register the user
		        	$created_user_id = $this->social_auth->social_register($username, '', $additional_data);
		        	
		        	if($created_user_id)
		        	{
						// Add Connection
						$add_connection = $this->connections_model->add_connection($created_user_id, 'facebook', $url_vars->access_token, $url_vars->secret, $facebook_user['id'], $facebook_user['name'], $_GET['session']);
						
						// Login In With
			        	if ($this->social_auth->social_login('facebook', $created_user_id, $url_vars->access_token, $url_vars->secret, $add_connection->connection_password)) 
			        	{
		        			$this->session->set_flashdata('message', "User created and logged in");
				        	redirect(base_url().'home', 'refresh');
				        }
				     	// Error Logging In  
				        else 
				        {
				        	$this->session->set_flashdata('message', "Login with Facebook in-correct");
				        	redirect("login", 'refresh');
				        }
		       		}
		       		else
		       		{
		        		// Redirect them back to the admin page
		        		$this->session->set_flashdata('message', "Error creating user & logging in");
		        		redirect("login", 'refresh');
		       		}
				}
			}
			else
			{
				redirect('connections/facebook', 'refresh');
			}
		}			
	}
	
	function extended()
	{
		if (!$this->config->item('facebook') && (!$this->config->item('facebook_extended'))) redirect(base_url(), 'refresh');
	
		$this->load->library('facebook');		

		$check_connection = $this->connections_model->check_connection_id($this->uri->segment(3));

		if (!$check_connection) redirect(base_url(), 'refresh');
		
		$user_update = FALSE;

		// SETS SESSION VALUES THAT ALLOWS Facebook API calls
		$this->facebook->getSessionDatabase($check_connection->token_whole);
		// GET USER INFO
		$facebook_user = $this->facebook->api('/me');
		// CONNECTION IS FROM EXISTING USER
		if ($check_connection->user_id != 0)
		{
			$user 			= $this->social_auth->get_user($check_connection->user_id);
			$user_update 	= TRUE;
			$user_id		= $user->user_id;
		}		
		// ELSE CREATE USER ACCOUNT AND UPDATE CONNECTION
		$username	= url_username($facebook_user['name'], 'none', true);		
		// IMAGE IS BLANK GRAB FROM FBOOK
		if (($check_connection->user_id == 0) || ($user->image == ''))
		{
			// GET ALL PHOTO ALBUMS
			$get_albums = $this->facebook->api(array('query' => 'SELECT name, aid FROM album WHERE owner='.$facebook_user['id'], 'method' => 'fql.query'));    
			$album				= array();
			$album_id			= NULL;
			$profile_picture	= array();
			// FIND THE aid  		
			foreach ($get_albums as $album):				
				if ($album['name'] == 'Profile Pictures'):
					$album_id = $album['aid'];				
					break;
				endif;
			endforeach;				
			// GET ALBUM Profile Pictures
			if ($album_id != '')
			{
				$get_profile_album = $this->facebook->api(array('query' => 'SELECT src_big FROM photo WHERE aid='.$album['aid'], 'method' => 'fql.query',));
										
				foreach ($get_profile_album as $profile_picture):			
					$user_new_picture = $profile_picture['src_big'];
					break;					
				endforeach;
						
				if ($profile_picture['src_big'] != '')
				{
	        		$this->load->model('image_model');													

					$image_full		= $user_new_picture;
					$image_name		= $username.'.'.pathinfo($image_full, PATHINFO_EXTENSION);
					$image_save		= $this->config->item('profile_raw_path').$image_name;

					$this->image_model->get_external_image($image_full, $image_save);

					$image_size 	= getimagesize($image_save);
					$image_width 	= $image_size[0];
					$image_height	= $image_size[1];
					$this->image_model->make_profile_images($image_name, $image_width, $image_height, $user_id); 						
					unlink($image_save);
				}
			}
		}
		
		if ($user_update)
		{
	    	$update_data = array(
	        				'name' 		 	=> $facebook_user['name'],
	        				'email'			=> $facebook_user['email'],
							'location'	 	=> $facebook_user['location']['name'],
							'bio' 		 	=> '',
							'url'	 	 	=> $facebook_user['link'],
							'image'		 	=> $image_name,
							'home_base'		=> 'facebook',
							'language'		=> $this->config->item('languages_default'),
							'time_zone'		=> $time_zone,
							'geo_enabled'	=> 0,
							'utc_offset' 	=> $utc_offset				        	
	    					);

	    	$this->social_auth->update_user($this->session->userdata('user_id'), $update_data);
		}
		// IS A NEW USER SO CREATE
		else
		{					
			// CONVERTS FACEBOOK TIMEZONE TO STANDARD
			foreach(timezones() as $key => $zone)
			{
				if ($facebook_user['timezone'] === $zone) $time_zone = $key;						
			}
			
	    	$utc_offset	= $facebook_user['timezone'] * 60 * 60;
	    						
	    	$additional_data = array(
	        				'name' 		 	=> $facebook_user['name'],
							'location'	 	=> $facebook_user['location']['name'],
							'bio' 		 	=> '',
							'url'	 	 	=> $facebook_user['link'],
							'image'		 	=> $image_name,
							'home_base'		=> 'facebook',
							'language'		=> $this->config->item('languages_default'),
							'time_zone'		=> $time_zone,
							'geo_enabled'	=> 0,
							'utc_offset' 	=> $utc_offset
	        				);

	    	$created_user_id = $this->social_auth->social_register($username, $additional_data);
	    	
	  		$user_id = $created_user_id;				
		}
		
    	if($user_id)
    	{
			// LOG USER IN WITH SOCIAL
        	if ($this->social_auth->social_login('facebook', $user_id, $check_connection->access_token, $check_connection->secret, $check_connection->connection_password)) 
        	{
    			$this->session->set_flashdata('message', "User created and logged in");
	        	redirect(base_url().'home', 'refresh');
	        }
	        else 
	        {
	        	$this->session->set_flashdata('message', "Login with Facebook in-correct");
	        	redirect("login", 'refresh');
	        }
   		}
   		else
   		{
    		$this->session->set_flashdata('message', "Error creating user & logging in");
    		redirect("login", 'refresh');
   		}		
	}
	
	function remove()
	{
		if (!$this->social_auth->logged_in()) redirect(base_url(), 'refresh');
	
		$this->connections_model->remove_connection($this->session->userdata('user_id'), 'facebook');
		
		redirect('settings/connections', 'refresh');
	}	

}