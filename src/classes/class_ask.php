<?php

    class ask {
        
        
        //start new questions process
        
         function register_session()
            {
                global $db,$conf;
                
                $ret_fin['ok'] = 0;
                $ret_fin['msg'] = 'Can not start new session.';
                
                //register new session for 2 partners and reply with ask_key
                  //create new user
                    
                    $level_txt = '';  //questions level
                    $levels_arr = $_REQUEST['level'];
                    if(is_array($levels_arr))
                        {
                           foreach($levels_arr as $level => $val)
                            {
                               if((is_numeric($level)) && ($val == 1))
                                {      
                                  if($level_txt != '')
                                    {
                                      $level_txt .= ',';   
                                    }                   
                                               
                                  $level_txt .= $level;   
                                }   
                            }   
                        }
                   
                   if($level_txt == '')
                    {
                        $level_txt = 1;  //questions level
                    }
                    else
                    {
                        $level_txt = trim($level_txt);  
                    }
               
                    
                    $stamp_now = time();
                    
                    //get user nicks, sex
                     $nick1 = trim(urldecode($_REQUEST['nick1']));
                     if($nick1 != '')
                        {
                            $nick1 = $db->quote_smart($nick1);                            
                        }
                        else
                        {
                            $nick1 = 'Partner 1';  
                        }
                    
                    $nick2 = trim(urldecode($_REQUEST['nick2']));
                     if($nick2 != '')
                        {
                            $nick2 = $db->quote_smart($nick2);                            
                        }
                        else
                        {
                            $nick2 = 'Partner 2';  
                        }
                    
                    //insert age/sex info - new
                    $age = trim(urldecode($_REQUEST['age']));
                    $sex = trim(urldecode($_REQUEST['sex']));
                    $age_partner = trim(urldecode($_REQUEST['age_partner']));
                    $sex_partner = trim(urldecode($_REQUEST['sex_partner']));
                    
                    $age = $db->quote_smart($age);     
                    $sex = $db->quote_smart($sex);
                    $age_partner = $db->quote_smart($age_partner);     
                    $sex_partner = $db->quote_smart($sex_partner);
                     
                    $ask_key = openssl_random_pseudo_bytes('16', $cstrong);
                    $ask_key = bin2hex($ask_key);
                    
                    $query = "INSERT INTO user (date_added,ask_key,username,display_name,levels,age,sex) VALUES ('$stamp_now','$ask_key','$ask_key','$nick1','$level_txt','$age','$sex')"; 
                    $db->query($query);
                    
                    $this_user_id = $db->last_id();
                    
                    
                    //create partner user with own key  
                    $ask_key_partner = openssl_random_pseudo_bytes('16', $cstrong);
                    $ask_key_partner = bin2hex($ask_key_partner);
                    
                    $query = "INSERT INTO user (date_added,ask_key,username,display_name,levels,age,sex) VALUES ('$stamp_now','$ask_key_partner','$ask_key_partner','$nick2','$level_txt','$age_partner','$sex_partner')"; 
                    $db->query($query);
                    
                    $partner_user_id = $db->last_id();
                    
                     
                    $results_key = openssl_random_pseudo_bytes('16', $cstrong);
                    $results_key = bin2hex($results_key);
                                    
                    //link two users
                    $query = "INSERT INTO user_link (main_user_id,slave_user_id,results_key) VALUES ('$this_user_id','$partner_user_id','$results_key')"; 
                    $db->query($query);
                    
                    $ret_fin['ok'] = 1;
                    $ret_fin['msg'] = 'Session started.';
                    $ret_fin['key'] = $ask_key;
                
                
                echo json_encode($ret_fin);
                
                
            }
       
        function start()
            {
                global $db,$js,$conf;
                
                
                $partner_user_id = 0;
                $this_user_id = 0;
                $results_key = '';
                $ask_key_partner = '';
                
                
                //check for existing user 
                $no_user_create = 0;
                $partner = 0;
                
                $key = trim($_REQUEST['key']);
                if($key != '')
                    {
                       
                      $key = $db->quote_smart($key);
                      
                      //checking if this user exists
                      $query = "SELECT id,display_name,levels FROM user WHERE ask_key='$key'";
                      $ret = $db->get_data($query);
                      if($ret['rows'] > 0)
                        {
                          //user exist
                          $this_user_id = $ret['data'][0]['id'];
                          $display_name = $ret['data'][0]['display_name'];
                          $levels = $ret['data'][0]['levels'];
                          
                          //check if this user already answered
                          $query = "SELECT COUNT(id) as countit FROM question_answer WHERE user_id='$this_user_id'";
                          $ret = $db->get_data($query); 
                          $countit = $ret['data'][0]['countit'];
                          
                          $answered = 0;
                          if($countit > 0)
                            {
                              //user already answered, display results
                              $answered = 1;   
                            }
               
                          
                          //making levels condition
                          $level_cond = '';
                          if($levels != '')
                            {
                              $levels_arr = explode(',',$levels);
                              for($i=0;$i<count($levels_arr);$i++)
                                {
                                  $level_id = $levels_arr[$i];   
                                  
                                  if(is_numeric($level_id))
                                    {
                                       if($level_cond != '')
                                        {
                                          $level_cond .= ' OR ';   
                                        } 
                                      $level_cond .= "level='$level_id'";  
                                    }
                               
                                }
                                
                                if($level_cond != '')
                                {
                                  $level_cond = ' AND ('.$level_cond.')';   
                                } 
                               
                            }
                          
                          $no_user_create = 1; 
                          
                          $ask_key = $key;
                          
                          $query = "SELECT main_user_id,slave_user_id,results_key FROM user_link WHERE slave_user_id='$this_user_id' OR main_user_id='$this_user_id'";
                          $ret = $db->get_data($query); 
                          if($ret['rows'] > 0)
                            {
                                
                               $main_user_id = $ret['data'][0]['main_user_id'];
                               $slave_user_id = $ret['data'][0]['slave_user_id'];
                               $results_key = $ret['data'][0]['results_key'];
                               
                               if($main_user_id == $this_user_id)
                                {
                                  //main user, get partner's key
                                  $query = "SELECT ask_key FROM user WHERE id='$slave_user_id'";
                                  $ret = $db->get_data($query);
                                  if($ret['rows'] > 0)
                                    {
                                      $ask_key_partner = $ret['data'][0]['ask_key'];   
                                    }
                                  
                                    
                                } 
                                else
                                {
                                  //partner
                                  $partner = 1;
                                  $partner_user_id = $this_user_id;
                                  $this_user_id = $main_user_id;   //make main user (load questions from main user, not partner)
                                     
                                }
                                
                            
                        
                
                
                //make 3 links (own,partner,results)
                $link_own = $conf['main_url'].'index.php?a=start&key='.$ask_key;
                $link_partner = $conf['main_url'].'index.php?a=start&key='.$ask_key_partner;
                $link_results = $conf['main_url'].'index.php?a=results&key='.$results_key;
                
               
                  if($partner == 1)
                        {
                           
                            echo '<div class="container" id="next_step"';
                            
                            if($answered != 1)
                            {
                                echo ' style="display:none"';
                            }
                       
                            echo '>
                      
                              <h2>Your answers have been saved and results are ready.</h2>
                              <P>We suggest you and your partner look at results together.
                              <P>
                              
                              <a href="'.$link_results.'" type="button" class="btn btn-success btn-lg">View results</a>
                              
                                <input type=hidden name=link_own id=link_own value="'.$link_own.'">
                                <input type=hidden name=link_partner id=link_partner value="'.$link_partner.'">
                                <input type=hidden name=link_results id=link_results value="'.$link_results.'">
                                <input type=hidden name=partner id=partner value="'.$partner.'">
                              
                              </div>
                              ';
                              
                      
                       
                        }
                        else
                        {
           
                    
                     echo '<div class="container" id="next_step"';
                            
                            if($answered != 1)
                            {
                                echo ' style="display:none"';
                            }
                       
                            echo '>
                      
                      <h2>Your answers have been saved.</h2>
                      <p>It\'s time for your partner now.
                      <P>
                      <a href="'.$link_partner.'" type="button" class="btn btn-success btn-lg">Start partner\'s questionnaire on this computer</a>
                      <a href="#" type="button" onclick="show_links();" class="btn btn-primary btn-lg">My partner is on different computer</a>
                      
                      <div id="partner_links" style="display:none">
                      
                       <div class="form-group">
                        <label for="partner_link">Send this link to your partner</label>
                        <input type=text id=partner_link class="form-control" readonly="readonly" value="'.$link_partner.'">
                       </div>
                       
                       <div class="form-group">
                        <label for="partner_link">Once your partner completes the questions, use this link for results</label>
                        <input type=text id=partner_link class="form-control" readonly="readonly" value="'.$link_results.'">
                       </div>   
                       
                        <a href="#" type="button" onclick="show_email();" class="btn btn-default">I want to email the link</a>
                         
                         
                         <div id="email_box" style="display:none;padding-top:20px;">
                         
                           <input type=hidden name=ask_key_email id=ask_key_email value="'.$ask_key_partner.'">
                         
                           <div class="alert alert-danger"><strong>We take your privacy seriously.</strong>
                            You don\'t have to email the link through us and expose your partner\'s email address.
                            Just copy/paste the link and send it to your partner yourself. If you still wish to send it through us, please enter email address below. 
                           </div>
                           
                            <label for="partner_email">Please enter your partner\'s email address</label>
                            
                           <div class="input-group"> 
                            <input type=email id=partner_email class="form-control" placeholder="Partner\'s email" value="">
                            <span class="input-group-btn">
                             <button class="btn btn-success" onclick="send_email();" type="button">Send link</button>
                             
                            </span>
                            
                           </div>   
                       
                         </div>
                         
                         <div id="email_box_msg" style="display:none;padding-top:20px;">
                         
                           <div class="alert alert-success"><strong>Email has been sent successfully.</strong>
                            <a href="'.$link_results.'" type="button" class="btn btn-default">Open results page</a>
                           </div>
                           
                         </div>
                         
                       
                      </div> 
                      
                      
                                <input type=hidden name=link_own id=link_own value="'.$link_own.'">
                                <input type=hidden name=link_partner id=link_partner value="'.$link_partner.'">
                                <input type=hidden name=link_results id=link_results value="'.$link_results.'">

                        
                     </div>';
                   
                   }
               
                  
                //making options
                echo '
                <script>
                 $(document).ready(function() {
                     
                     $("#top_progress").show();
                     
                     ';
                     
                     if(($partner != 1) && ($answered != 1))
                        {
                            echo ' 
                                $("#add_question_link").show();
                            ';
                        }
                     
                     echo '
    
                });
                </script>';
                
    
                
                
                    
                
                //loading answers options
                $query = "SELECT id,name,show_scale FROM answer WHERE status='enabled' AND (user_id='0' OR user_id='$this_user_id') ORDER BY sort,name";
                $ret_answers = $db->get_data($query);
                
                //display javascript
                echo $js->ask();
                
                //display all questions
                
                echo '<div class="container" id="questions"';
                
                if($answered == 1)
                    {
                      echo ' style="display:none"';  
                    }
                                
                echo '>

                <h2>'.$display_name.'\'s turn</h2>
                
                
                <div class="panel-group" id="accordion">
                
                <form name=main_form id=main_form>
                <input type=hidden name=ask_key id=ask_key value="'.$ask_key.'">
                
                <input type=hidden name=link_own id=link_own value="'.$link_own.'">
                <input type=hidden name=link_partner id=link_partner value="'.$link_partner.'">
                <input type=hidden name=link_results id=link_results value="'.$link_results.'">
                <input type=hidden name=partner id=partner value="'.$partner.'">
                
                
                ';
                
                
                
                $query = "SELECT id,name,description FROM groups WHERE status='enabled' AND (user_id='0' OR user_id='$this_user_id') ORDER BY sort,name";
                
                $ret = $db->get_data($query);
                if($ret['rows'] > 0)
                    {
                      
                       $group_index = 0;
                       
                       for($i=0;$i<$ret['rows'];$i++)
                        {
                          
                            $group_id = $ret['data'][$i]['id'];
                            $group_name = $ret['data'][$i]['name'];
                            $group_desc = $ret['data'][$i]['description'];
                            
                           
                         
                                
                                //listing all questions from this group
                                $query = "SELECT id,body,user_id FROM question WHERE group_id='$group_id' AND status='enabled' AND (user_id='0' OR user_id='$this_user_id') $level_cond ORDER BY sort";
                                $ret_data = $db->get_data($query);
                                if($ret_data['rows'] > 0)
                                    {
                                       
                                     $collapse_txt = '';
                                     if($first_shown != 1)
                                        {    
                                            $collapse_txt = 'in';
                                            $first_shown = 1;
                                        }
                                       
                            
                             
                                          echo' <div class="panel panel-default">
                                          <div class="panel-heading">
                                            <h3 class="panel-title"><a data-toggle="collapse" data-parent="#accordion" href="#collapse_'.$group_id.'"><span id="current_group_'.$group_id.'">'.$group_name.'</span></a></h3>
                                          </div>
                                          
                                          <div id="collapse_'.$group_id.'" class="panel-collapse collapse '.$collapse_txt.' group_index_'.$group_index.'">
                                          <div class="panel-body">
                                          
                                          <input type=hidden class="current_groups" value="'.$group_id.'">
                                          
                                          <table class="table table-striped"  id="group_table_'.$group_id.'">
                                            ';
                                            
                                       for($k=0;$k<$ret_data['rows'];$k++)
                                        {
                                           
                                           $q_id = $ret_data['data'][$k]['id'];
                                           $q_body = $ret_data['data'][$k]['body'];
                                           $q_user_id = $ret_data['data'][$k]['user_id'];
                                           
                                           
                                           //display question
                                           echo '<tr id="question_row_'.$q_id.'">
                                                    <td valign=top width=40%><span id="question_'.$q_id.'">'.$q_body.'</span>';
                                                    
                                                    if(($q_user_id == $this_user_id) && ($partner != 1))
                                                        {
                                                            echo '<br><button type="button" onclick="questions_delete('.$q_id.');" class="btn btn-danger">Delete</button>';
                                                        }
                                                    
                                                    echo '</td>
                                                    <td nowrap valign=top align=right width=60%>
                                                    
                                                        <input type=hidden class="current_questions_'.$group_id.'" value="'.$q_id.'">
                                                        <input type=hidden  id="scale_'.$q_id.'" name="scale['.$q_id.']" value="3">

                                                     <div id="scale_box_'.$q_id.'" style="display:none">
                                                      Your interest level:
                                                      <span class="rating">
                                                          
                                                          <span class="star" onclick="set_scale('.$q_id.',5);" id="star_5_'.$q_id.'"></span>
                                                          <span class="star" onclick="set_scale('.$q_id.',4);" id="star_4_'.$q_id.'"></span>
                                                          <span class="star filled" onclick="set_scale('.$q_id.',3);" id="star_3_'.$q_id.'"></span>                                                          
                                                          <span class="star filled" onclick="set_scale('.$q_id.',2);" id="star_2_'.$q_id.'"></span>
                                                          <span class="star filled" onclick="set_scale('.$q_id.',1);" id="star_1_'.$q_id.'"></span>
                                                      </span>
                                                 
                                                        </div>

                                                        <div class="btn-group" data-toggle="buttons">
                                                          
                                                        
                                                    
                                                  
                                                    ';
                                                    
                                                    /*
                                                     
                                                        
                                                         <input id="scale_slider_'.$q_id.'" data-slider-id="scale_slider_'.$q_id.'" type="text" data-slider-min="0" data-slider-max="5" data-slider-step="1" data-slider-value="3"/>




                                                       
                                                       */
                                                        
                                                    
                                                    /*
                                                     <input type="number" name="scale" id="scale_'.$q_id.'" value=2 class="rating"/>
                                                      <select name="answer['.$q_id.']" id="answer_'.$q_id.'_'.$answer_id.'" class="select_answer answer">
                                                    <option value="0">Please select</option>
                                                    */
                                                    
                                                        for($m=0;$m<$ret_answers['rows'];$m++)
                                                            {
                                                              $answer_id = $ret_answers['data'][$m]['id'];
                                                              $answer_name = $ret_answers['data'][$m]['name'];
                                                              $show_scale = $ret_answers['data'][$m]['show_scale'];
                                                              
                                                              $m2 = $m+1;
                                                              
                                                            /*  
                                                              echo ' 
                                                                    <option value='.$answer_id.'> '.$answer_name.'</option>
                                                                ';
                                                              */
                                                                 
                                                              echo '
                                                              <label class="btn btn-primary">
                                                                <input type="radio" class="answer" onchange="check_answer('.$q_id.','.$group_id.','.$group_index.','.$show_scale.')" name="answer['.$q_id.']" data-qid="'.$q_id.'" value="'.$answer_id.'" id="answer_'.$q_id.'_'.$answer_id.'">'.$answer_name.'
                                                              </label>
                                                              ';
                                                          
                                                            }
                                                       
                                                          
                                                      /*
                                                      echo '</select>
                                                        ';
                                                    */
                                                    
                                                    
                                                      
                                                    echo '</div>
                                                    ';
                                                    
                                                    
                                                    echo '
                                                    
                                                     </td></tr>';
                                                 
                                           $group_index++;
                                            
                                        }
                                   
                                                 echo '</table>
                                             </div>
                                          </div>
                                        </div>';
                                        
                                    }
                                   

                        }
                        
                        
                    }
                
                
                echo '
                
               
                 
                 ';
                 
                 
               
                echo '</form>
                
                  </div>
                
                </div>
                
                <div id="questions_buttons" class="container"';
                
                
                if($answered == 1)
                    {
                      echo ' style="display:none"';   
                    }
               
                echo '>
                 ';
                 
                 if($partner != 1)
                    {
                      //   echo '<button type="button" class="btn btn-default" onclick="questions_add();">Add question</button>';
                    }
               
               
                echo '
                <button type="button" class="btn btn-success btn-lg" onclick="save_answers();">Save and continue</button>
                
                 
                </div>
                
                ';
                
                    } //user link found
                    else
                    {
                        echo "<P>This key is no longer linked to users.
                        <p><a href=".$conf['main_url']."index.php class=\"btn btn-primary\">Click here to start from the beginning</a> ";                            
                    }
               
                
                 } //user found
                 else
                 {
                    echo "<P>This key is no longer available.
                    <p><a href=index.php class=\"btn btn-primary\">Click here to start from the beginning</a> ";    
                 }
                
               } //key available
               else
               {
                 echo "<P><a href=index.php class=\"btn btn-primary\">Click here to start from the beginning</a> ";   
               }
                
            }
        
       
       function save_data()
            {
                global $db;
                
                $ret_fin['ok'] = 0;
                $ret_fin['msg'] = 'Can not save answers';
             
               //save answers for user
               $ask_key = trim($_REQUEST['ask_key']);
               
                if($ask_key != '')
                    {
                       $ask_key = $db->quote_smart($ask_key);
                       
                       //checking if this user exists
                       $query = "SELECT id FROM user WHERE ask_key='$ask_key'";
                       $ret = $db->get_data($query);
                       if($ret['rows'] > 0)
                        {
                          //user exists, saving answers
                          $this_user_id = $ret['data'][0]['id'];
                          
                          //check if answers already present - do not allow saving second time
                          $query = "SELECT COUNT(id) as countit FROM question_answer WHERE user_id='$this_user_id'";
                          $ret = $db->get_data($query);
                          $countit = $ret['data'][0]['countit'];
                          
                          if($countit > 0)
                            {
                              $ret_fin['msg'] = 'Answers has already been saved.';  
                            }
                            else
                            {
                          
                              
                            
                              //saving answers
                              $answer_arr = $_REQUEST['answer'];
                              $scale_arr = $_REQUEST['scale'];
                              
                              
                              if(is_array($answer_arr))
                                {
                                    $ins_sql = '';
                                    $stamp_now = time();
                                    
                                    foreach($answer_arr as $q_id => $a_id)
                                        {
                                            
                                            if((is_numeric($q_id)) && (is_numeric($a_id)))
                                                {
                                                    //saving answer
                                                    if($ins_sql != '')
                                                        {
                                                            $ins_sql .= ',';
                                                        }
                                                   
                                                    $scale_level = $scale_arr[$q_id];
                                                    if(!is_numeric($scale_level))
                                                        {
                                                          $scale_level = 3;   
                                                        }
                                                   
                                                    $ins_sql .= "('$this_user_id','$q_id','$a_id','$stamp_now','$scale_level')";
                                                    
                                                } 
                                            
                                        }   
                                    
                                   
                                    
                                    if($ins_sql != '')
                                        {
                                           $query = "INSERT INTO question_answer (user_id,question_id,answer_id,date_added,interest_scale) VALUES $ins_sql";  
                                           $db->query($query); 
                                           
                                           
                                           $ret_fin['ok'] = 1;
                                           $ret_fin['msg'] = 'Answers saved successfully.';
                                            
                                        }
                                }
                                else
                                {
                                  $ret_fin['msg'] = 'Please choose at least one answer.';  
                                }
                              
                              
                              
                          
                            }
                          
                             
                            
                        }
                          
                        
                    }
               
              
               
               echo json_encode($ret_fin);
                
            }
                
          
           function results()
            {
                global $db,$js,$conf,$ads,$patreon;
                
                //display results 
                
                $key = trim($_REQUEST['key']);
                
                $something_shown = 0;
                
                echo '
                 <h2>Results</h2>
                 <P>Bookmark this page and complete your activities one by one!</p>';
                 
                 if($key != '')
                    {
                       $key = $db->quote_smart($key);
                       
                       $query = "SELECT * FROM user_link WHERE results_key='$key'";
                       $ret = $db->get_data($query);
                       if($ret['rows'] > 0)
                        {
                            $this_user_id = $ret['data'][0]['main_user_id'];
                            $slave_user_id = $ret['data'][0]['slave_user_id'];
                            
                            //load main user key
                            $query = "SELECT ask_key FROM user WHERE id='$this_user_id'";
                            $ret = $db->get_data($query);
                            if($ret['rows'] > 0)
                                {
                                   $ask_key = $ret['data'][0]['ask_key'];
                                   
                                   echo '<input type=hidden name=ask_key id=ask_key value="'.$ask_key.'">';
                                    
                                }
                            
                            
                            
                            //listing questions of main user
                            //loading answers options
                            $hide_answers = array();
                            $answers_arr = array();
                            $answers_user_arr = array();
                            $done_arr = array();
                            $show_scale_arr = array();
                            
                            $query = "SELECT id,name,hide_result,show_scale FROM answer WHERE status='enabled' AND (user_id='0' OR user_id='$this_user_id') ORDER BY sort,name";
                            $ret_answers = $db->get_data($query);
                            if($ret_answers['rows'] > 0)
                                {
                                   for($i=0;$i<$ret_answers['rows'];$i++)
                                    {
                                       $hide_result = $ret_answers['data'][$i]['hide_result'];
                                       $answers_arr[$ret_answers['data'][$i]['id']] = $ret_answers['data'][$i]['name'];
                                       
                                       $show_scale_arr[$ret_answers['data'][$i]['id']] = $ret_answers['data'][$i]['show_scale'];
                                       
                                       
                                       
                                       if($hide_result == 1)
                                        {
                                           //hide question when this answer selected by any party
                                          $hide_answers[$ret_answers['data'][$i]['id']] = 1;     
                                            
                                        }
                                    }
                                }
                            
                            
                            //loading answers for each user                           
                            $query = "SELECT user_id,question_id,answer_id,done,interest_scale FROM question_answer WHERE user_id='$this_user_id' OR user_id='$slave_user_id'";
                            $ret = $db->get_data($query);
                            if($ret['rows'] > 0)
                                {
                                   for($i=0;$i<$ret['rows'];$i++)
                                    {
                                         $question_id = $ret['data'][$i]['question_id'];
                                         $answer_id = $ret['data'][$i]['answer_id'];
                                         $user_id = $ret['data'][$i]['user_id'];
                                         $done = $ret['data'][$i]['done'];
                                         
                                         $scale_val = $ret['data'][$i]['interest_scale'];
                                         
                                         $answers_user_arr[$user_id][$question_id] = $answer_id;
                                         
                                         $scale_arr[$user_id][$question_id] = $scale_val;
                                         
                                         if($done == 1)
                                            {
                                                $done_arr[$question_id] = 1;
                                            }
                                         
                                    }                                     
                                }
                            
                            //both users answered questions
                            if((isset($answers_user_arr[$this_user_id])) && (isset($answers_user_arr[$slave_user_id])))
                                {
                                    
                                    
                                    $this_user_name = 'Partner 1';
                                    $slave_user_name = 'Partner 2';
                                    
                                    //get user nicknames
                                    $query = "SELECT id,display_name FROM user WHERE id='$this_user_id' OR id='$slave_user_id'";
                                    $ret = $db->get_data($query);
                                    if($ret['rows'] > 0)
                                        {
                                          for($i=0;$i<$ret['rows'];$i++)
                                            {              
                                              if($ret['data'][$i]['id'] == $this_user_id)
                                                {
                                                  $this_user_name = $ret['data'][$i]['display_name'];   
                                                }
                                              if($ret['data'][$i]['id'] == $slave_user_id)
                                                {
                                                  $slave_user_name = $ret['data'][$i]['display_name'];   
                                                  
                                                }                                    
                                            }  
                                            
                                        }
                                    
                                    
                                    //display all questions
                                    
                                   // echo '<div class="container"> ';
                                    
                                    
                                   
                                   /* 
                                    echo '<pre>';
                                    print_r($answers_user_arr);
                                    print_r($hide_answers);
                                    */ 
                                    
                                   
                                    
                                    $query = "SELECT id,name,description FROM groups WHERE status='enabled' AND (user_id='0' OR user_id='$this_user_id') ORDER BY sort,name";
                                    $ret = $db->get_data($query);
                                    if($ret['rows'] > 0)
                                        {
                                           
                                           for($i=0;$i<$ret['rows'];$i++)
                                            {
                                              
                                                $group_id = $ret['data'][$i]['id'];
                                                $group_name = $ret['data'][$i]['name'];
                                                $group_desc = $ret['data'][$i]['description'];
                                                
                                              
                                                
                                                    
                                                    //listing all questions from this group
                                                    $query = "SELECT id,body FROM question WHERE group_id='$group_id' AND (user_id='0' OR user_id='$this_user_id') ORDER BY sort";
                                                    $ret_data = $db->get_data($query);
                                                    if($ret_data['rows'] > 0)
                                                        {
                                                           
                                                           echo' <div class="panel panel-default" id="group_'.$group_id.'">
                                                          <div class="panel-heading">
                                                            <h3 class="panel-title">'.$group_name.'</h3>
                                                          </div>
                                                          
                                                          
                                                          <div class="panel-body">
                                                          <table class="table table-striped">
                                                          <tr>
                                                            <th width=60%>Activity</th>
                                                            <th width=20%>'.$this_user_name.'\'s answer</th>
                                                            <th width=20%>'.$slave_user_name.'\'s answer</th>
                                                            <th>Done</th>
                                                          </tr>
                                                            ';
                                                    
                                                           $shown = 0;
                                                           
                                                           for($k=0;$k<$ret_data['rows'];$k++)
                                                            {
                                                               
                                                               $q_id = $ret_data['data'][$k]['id'];
                                                               $q_body = $ret_data['data'][$k]['body'];
                                                               
                                                               
                                                               
                                                               //$answers_arr
                                                                 //check if any questions were answered positively, get list of positive answers
                                                                $answer_id_main = $answers_user_arr[$this_user_id][$q_id];
                                                                $answer_id_partner = $answers_user_arr[$slave_user_id][$q_id];
                                                                
                                                                $scale_main = $scale_arr[$this_user_id][$q_id];
                                                                $scale_partner = $scale_arr[$slave_user_id][$q_id];
                                                                
                                                                $show_scale = $show_scale_arr[$answer_id_main]; 
                                                                
                                                              // echo "DD: $show_scale ";
                                                              //  print_r($show_scale_arr);
                                                                
                                                                $hide_question = 0;
                                                                
                                                                if(($hide_answers[$answer_id_main] == 1) || ($hide_answers[$answer_id_partner] == 1) || ($answer_id_main == '') || ($answer_id_partner == '') || ($answer_id_main == 0) || ($answer_id_partner == 0))
                                                                    {
                                                                      $hide_question = 1;  
                                                                    }   
                                                               
                                                               
                                                                   if($hide_question != 1)
                                                                   {
                                                                                 
                                                                           //display question
                                                                           echo '<tr>
                                                                                    <td valign=top>'.$q_body.'</td>                                                                
                                                                                    <td valign=top>'.$answers_arr[$answer_id_main];
                                                                                    
                                                                                     if($show_scale == 1)
                                                                                        {
                                                                                        
                                                                                          echo '<br><span class="rating">';
                                                                                          
                                                                                           //for($w=1;$w<6;$w++)
                                                                                           for($w=5; $w>=1; $w-=1)                                                                                            
                                                                                            {
                                                                                              echo '<span class="star';
                                                                                              
                                                                                               if($scale_main >= $w)
                                                                                                {
                                                                                                    echo ' filled';
                                                                                                }
                                                                                           
                                                                                              echo '"></span>';  
                                                                                            }
                                                                                          
                                                                                          echo '</span>';
               
  
                                                                                        }
                                                                                    
                                                                                    echo '</td>
                                                                                    <td valign=top>'.$answers_arr[$answer_id_partner];
                                                                                    
                                                                                    
                                                                                     if($show_scale == 1)
                                                                                        {
                                                                                        
                                                                                          echo '<br><span class="rating">';
                                                                                          
                                                                                           //for($w=1;$w<6;$w++)
                                                                                           for($w=5; $w>=1; $w-=1)                                                                                            
                                                                                            {
                                                                                              echo '<span class="star';
                                                                                              
                                                                                               if($scale_partner >= $w)
                                                                                                {
                                                                                                    echo ' filled';
                                                                                                }
                                                                                           
                                                                                              echo '"></span>';  
                                                                                            }
                                                                                          
                                                                                          echo '</span>';
               
  
                                                                                        }
                                                                                    
                                                                                   
                                                                                    
                                                                                    
                                                                                    echo '</td>
                                                                                    <td valign=top><input type=checkbox id="done_'.$q_id.'" value=1 ';
                                                                                    
                                                                                    if($done_arr[$q_id] == 1)
                                                                                        {
                                                                                            echo ' checked ';
                                                                                        }
                                                                                    
                                                                                    echo ' onchange="set_done('.$q_id.');"></td>
                                                                                 </tr>';
                                                                                 
                                                                          $shown = 1;  
                                                                          $something_shown = 1; 
                                                                                    
                                                                   } 
                                                               
                                                                
                                                            }
                                                       
                                                            if($shown != 1)
                                                                {
                                                                  //no questions match in this group
                                                                  echo '<tr><td colspan=4>No activities in this category
                                                                  
                                                                  <script>
                                                                    $("#group_'.$group_id.'").hide();
                                                                  </script>
                                                                  
                                                                  </td></tr>';   
                                                                    
                                                                }
                                                            
                                                              echo '
                                                               
                                                             
                                                              
                                                              
                                                              ';
                                                              
                                                             //insert ads at the end of each group
                                                            if((is_array($ads['group'][$group_name])) && (count($ads['group'][$group_name]) > 0))
                                                                {
                                                                     
                                                                   for($w=0;$w<count($ads['group'][$group_name]);$w++)
                                                                    {
                                                                       $ads_txt = $ads['group'][$group_name][$w];
                                                                       
                                                                       echo '<tr><td colspan=4>'.$ads_txt.'</td></tr>'; 
                                                                    }   
                                                                }
                                                                
                                                              echo ' 
                                                                </table> 
                                                        
                                                              </div>
                                                            </div>';
                                                            
                                                            
                                                           
                    
                    
                                                        }
                                                        
                                                    
                                                    
                                                  
                    
                                            }
                                            
                                            
                                            //footer, ask to support on patreon
                                            if($something_shown == 1)
                                            {
                                                if($patreon['footer'] != '')
                                                    {
                                                      echo $patreon['footer'];   
                                                    }
                                                
                                            }
                                            
                                        }
                                   
                                 //  echo '</div>';
                                 
                                 if($something_shown != 1)
                                    {
                                      echo "<P>There are no matching activities to display.</p>";   
                                    }
                                   
                            
                            } 
                            else
                            {
                              //some user didn't yet answered
                              echo '<div class="alert alert-warning"><strong>No results are yet available.</strong>
                              <p>Both partners must answer the questions. Please refresh this page once your partner answered the questions.</div>';
                                 
                            }
                            
                            
                            
                        }
                        else
                        {
                          echo '<P>No results available for this link.';  
                        }
                       
                       
                        
                    }
                    else
                    {
                      echo '<P>No results available for this link.';   
                    }
                 
                 echo '
                
                <P><a href='.$conf['main_url'].'index.php class="btn btn-primary btn-lg">Go to homepage</a>
                 ';
                
                
            }       
    
    
      function send_partner_email()
        {
          global $db,$conf;
          
          $ret_fin['ok'] = 0;
          $ret_fin['msg'] = 'Can not send email.';
                
          $key = trim($_REQUEST['ask_key']);
          $email = trim(urldecode($_REQUEST['email']));
          
          if(($key != '') && ($email != ''))
            {
              //check if key is valid
              $key = $db->quote_smart($key);
              $query = "SELECT COUNT(id) as countit FROM user WHERE ask_key='$key'";
              $ret = $db->get_data($query);
              $countit = $ret['data'][0]['countit'];
              
              if($countit > 0)
                {
                    //valid key, validate email
                    if($db->validate_email($email))
                        {
                            //sending email
                            $link_partner = $conf['main_url'].'index.php?a=start&key='.$key;
                            
                            $subject = 'Link from your partner.';
                            $body = 'Hello,
                            
Your partner wants you to complete your part of questionnaire on '.$conf['main_name'].' website.
Please follow this link to start: '.$link_partner.'

Best Regards,
'.$conf['main_name'].' Team
'.$conf['main_url'].'
';
                        
                        $headers = 'From: '.$conf['email_from']. "\r\n" .
                            'Reply-To: '.$conf['email_from']. "\r\n";                            
                        
                           if(mail($email, $subject, $body, $headers))
                            {

                                $ret_fin['ok'] = 1;
                                $ret_fin['msg'] = 'Email sent successfully.';
                            }
                            
                        }
                        else
                        {
                          $ret_fin['msg'] = 'Can not send email: invalid email.'; 
                        } 
                    
                }
                else
                {
                    $ret_fin['msg'] = 'Can not send email: invalid access key.';
                }
               
                 
                
            }  
            
          echo json_encode($ret_fin); 
            
        }    
        
      
       function add_questions()
        {
            
          global $db,$conf;
          
          $ret_fin['ok'] = 0;
          $ret_fin['msg'] = 'Can not add question.';
                
          $key = trim($_REQUEST['ask_key']);
          
            if($key != '') 
            {
              //check if key is valid
              $key = $db->quote_smart($key);
              $query = "SELECT id FROM user WHERE ask_key='$key'";
              $ret = $db->get_data($query);
              
              
              if($ret['rows'] > 0)
                {
                   $user_id = $ret['data'][0]['id'];
                    
                    //key valid
                   $new_data_arr = $_REQUEST['new_data'];
                   $allow_publish = $_REQUEST['allow_publish'];
                   if($allow_publish != 1)
                    {
                      $allow_publish = 0;   
                    }
               
                   $group_id = $new_data_arr['group'];
                   $group_custom = trim(urldecode($new_data_arr['group_custom']));
                   $body = trim(urldecode($new_data_arr['body']));
                   $position = $new_data_arr['position'];
                   
                   //validating
                   if($body == "")
                    {
                      
                      $e = 1;
                      $ret_fin['msg'] = 'Please type question text.';
                         
                    }
               
                   if(($group_id == 'new') && ($group_custom == ''))
                    {
                      $e = 1;
                      $ret_fin['msg'] = 'Please type new category name.';  
                    }
               
                   if($group_id != 'new')
                    {
                       if((!is_numeric($group_id)) || ($group_id == 0))
                        {
                           $e = 1;
                           $ret_fin['msg'] = 'Please select category.';   
                        } 
                    } 
                    
                    if(($position != 'last') && ($position != 'first') && (!is_numeric($position)))
                    {
                        $position = 'last';
                    } 
               
                
                    if($e != 1)
                        {
                          //adding custom question 
                          $body = $db->quote_smart($body); 
                          $stamp_now = time();
                          $group_new = 0;
                          $sort_question = 2000; //last question
                          
                          if($group_id == 'new')
                            {
                              //adding custom group first
                              $group_custom = $db->quote_smart($group_custom); 
                              $sort = 1000;  //custom sort, group goes last 
                              
                              $query = "INSERT INTO groups (user_id,status,name,sort) 
                              VALUES ('$user_id','enabled','$group_custom','$sort')";
                              
                              $db->query($query);
                              $group_id = $db->last_id();
                              
                              $sort = 50;
                              $group_new = 1;
                                
                            }
                            else
                            {
                              //making question sort
                             
                             
                              if($position == 'last') 
                                {
                                  //must be last question in this group
                                  //get last question's sort
                                  $query = "SELECT sort FROM question WHERE group_id='$group_id' ORDER BY sort DESC LIMIT 0,1";
                                  $ret = $db->get_data($query);
                                  $prev_sort = $ret['data'][0]['sort'];
                                  
                                  if(is_numeric($prev_sort))
                                    {
                                      $sort_question = $prev_sort+50; 
                                    }
                                }
                              elseif($position == 'first') 
                                {
                                  //must be last question in this group
                                  //get last question's sort
                                  $query = "SELECT sort FROM question WHERE group_id='$group_id' ORDER BY sort LIMIT 0,1";
                                  $ret = $db->get_data($query);
                                  $prev_sort = $ret['data'][0]['sort'];
                                  
                                  if(is_numeric($prev_sort))
                                    {
                                      $sort_question = $prev_sort-50; 
                                    }
                                }
                              elseif(is_numeric($position)) 
                                {
                                   
                                  $query = "SELECT sort FROM question WHERE group_id='$group_id' AND id='$position'";
                                  $ret = $db->get_data($query);
                                  $prev_sort = $ret['data'][0]['sort'];
                                  
                                  if(is_numeric($prev_sort))
                                    {
                                      $sort_question = $prev_sort+3; 
                                    }
                                
                                }
                            
                                 
                            }
                       
                          //adding new question
                          $query = "INSERT INTO question (user_id,group_id,language_id,date_added,body,status,allow_publish,sort)
                          VALUES ('$user_id','$group_id','".$conf['lang_id']."','$stamp_now','$body','enabled','$allow_publish','$sort_question')";
                          
                          
                          $db->query($query);
                          
                          $q_id = $db->last_id();
                          
                          //make question html tr line
                          $query = "SELECT id,name FROM answer WHERE status='enabled' AND (user_id='0' OR user_id='$user_id') ORDER BY sort,name";
                          $ret_answers = $db->get_data($query);
                
                          $body = '<tr id="question_row_'.$q_id.'">
                            <td valign=top width=50%><span id="question_'.$q_id.'">'.$body.'</span><br><button type="button" onclick="questions_delete('.$q_id.');" class="btn btn-danger">Delete</button></td>
                            <td nowrap valign=top align=right width=50%>                            
                                <input type=hidden class="current_questions_'.$group_id.'" value="'.$q_id.'">
                                <div class="btn-group" data-toggle="buttons">';
                            
                                for($m=0;$m<$ret_answers['rows'];$m++)
                                    {
                                      $answer_id = $ret_answers['data'][$m]['id'];
                                      $answer_name = $ret_answers['data'][$m]['name'];
                                   
                                      $body .= '
                                      <label class="btn btn-primary">
                                        <input type="radio" class="answer" name="answer['.$q_id.']" value="'.$answer_id.'" id="answer_'.$q_id.'_'.$answer_id.'">'.$answer_name.'
                                      </label>
                                      ';
                                  
                                    }                            
                              
                          $body .= '</div></td></tr>';
                          
                          
                          $ret_fin['ok'] = 1;
                          $ret_fin['msg'] = 'Question added successfully.';
                          
                          $ret_fin['position'] = $position;
                          
                          $ret_fin['body'] = $body;
                          $ret_fin['group_id'] = $group_id;  
                          $ret_fin['group_new'] = $group_new;                            
                          
                          if($group_new == 1)
                            {                                
                                $ret_fin['group_name'] = $group_custom;
                            }
                
                             
                        }
                    
                    
                }
            }
          
          
          echo json_encode($ret_fin);
                 
        }  
        
      
       function del_questions()
        {
            
          global $db,$conf;
          
          $ret_fin['ok'] = 0;
          $ret_fin['msg'] = 'Can not delete question.';
                
          $key = trim($_REQUEST['ask_key']);
          $q_id = trim($_REQUEST['id']);
          
            if(($key != '') && (is_numeric($q_id)))
            {
              //check if key is valid
              $key = $db->quote_smart($key);
              $query = "SELECT id FROM user WHERE ask_key='$key'";
              $ret = $db->get_data($query);
              
              
              if($ret['rows'] > 0)
                {
                   $user_id = $ret['data'][0]['id'];
                   
                   //deleting question
                   $query = "DELETE FROM question WHERE id='$q_id' AND user_id='$user_id'";
                   $db->query($query);
                   
                   $ret_fin['ok'] = 1;
                   $ret_fin['id'] = $q_id;
                   $ret_fin['msg'] = 'Question deleted successfully.';
                   
                }
             }  
        
        
          echo json_encode($ret_fin);
                 
        }  
        
        
        function list_questions()
        {
            
          global $db, $conf;
          
          //listing all questions
          
             //display all questions
                                    
                echo ' 
                 <h2>Questions you will be asked</h2>
                 <P>You can also add any number of your own questions and categories before your partner starts.</p>
                '; 
             
               
                
                $query = "SELECT id,name,description FROM groups WHERE status='enabled' AND user_id='0' ORDER BY sort,name";
                $ret = $db->get_data($query);
                if($ret['rows'] > 0)
                    {
                       
                       for($i=0;$i<$ret['rows'];$i++)
                        {
                          
                            $group_id = $ret['data'][$i]['id'];
                            $group_name = $ret['data'][$i]['name'];
                            $group_desc = $ret['data'][$i]['description'];
                            
                          
                            
                                
                                //listing all questions from this group
                                $query = "SELECT id,body,level FROM question WHERE group_id='$group_id' AND user_id='0'";
                                $ret_data = $db->get_data($query);
                                if($ret_data['rows'] > 0)
                                    {
                                       
                                       echo' <div class="panel panel-default">
                                      <div class="panel-heading">
                                        <h3 class="panel-title">'.$group_name.'</h3>
                                      </div>
                                      
                                      
                                      <div class="panel-body">
                                      <table class="table table-striped">
                                      
                                        ';
                                
                                       $shown = 0;
                                       
                                       for($k=0;$k<$ret_data['rows'];$k++)
                                        {
                                           
                                           $q_id = $ret_data['data'][$k]['id'];
                                           $q_body = $ret_data['data'][$k]['body'];
                                           $level = $ret_data['data'][$k]['level'];
                                           
                                           $label_txt = '';
                                           
                                           if($level == 2)
                                            {
                                              //advanced
                                              $label_txt = ' <span class="label label-danger">Advanced</span>';   
                                            }
                                            
                                           //display question
                                           echo '
                                                   <tr> <td valign=top>'.$q_body.$label_txt.'</td>                                                                
                                                   
                                                 </tr>';
                                                 
                                              
                                            
                                        }
                                    
                                        
                                          echo '
                                           
                                          </table>
                                    
                                          </div>
                                        </div>';


                                    }
                                    
                                
                                
                              

                        }
                        
                        
                    }
               
               
                echo '<a href='.$conf['main_url'].'index.php class="btn btn-primary btn-lg">Go back</a>';                   
          
        }
   
   
   function contact()
        {
            
          global $db,$conf;
          
          $ret_fin['ok'] = 0;
          $ret_fin['msg'] = 'Can not send message.';
                
          //sending contact email
          $data_arr = $_REQUEST['contact'];
          
          if(is_array($data_arr))
            {
                $body = trim(urldecode($data_arr['body']));
                $email = trim(urldecode($data_arr['email']));
                $name = trim(urldecode($data_arr['name']));
                
                //quoting 
                if(($name != '') && ($body != ''))
                    {
                      $body = $db->quote_smart($body);  
                      $name = $db->quote_smart($name);
                      $email = $db->quote_smart($email);
                      
                       $subject = 'Contact request from '.$conf['main_name'];
                       $body = 'Hello,
                            
New contact request received on '.$conf['main_name'].' website.
-------------------------
Name: '.$name.'
Email: '.$email.'
Message: '.$body.'
-------------------------

Best Regards,
'.$conf['main_name'].' Team
'.$conf['main_url'].'
';
                        
                        $headers = 'From: '.$conf['email_from']. "\r\n" .
                            'Reply-To: '.$conf['email_from']. "\r\n";                            
                        
                           if(mail($conf['email_contact'] , $subject, $body, $headers))
                            {

                                $ret_fin['ok'] = 1;
                                $ret_fin['msg'] = 'Email sent successfully.';
                            }
                       
                      
                        
                    }
                
                
            }
        
        
          echo json_encode($ret_fin);
                 
        }  
        
        
   function set_done()
        {
            
          global $db,$conf;
          
          $ret_fin['ok'] = 0;
          $ret_fin['msg'] = 'Can not set as done.';
          
          
          $done = trim($_REQUEST['done']);       
          $key = trim($_REQUEST['key']);
          $q_id = trim($_REQUEST['q_id']);
          
            if(($key != '') && (is_numeric($q_id)))
            {
              //check if key is valid
              $key = $db->quote_smart($key);
              $query = "SELECT id FROM user WHERE ask_key='$key'";
              $ret = $db->get_data($query);
              
              
              if($ret['rows'] > 0)
                {
                   $user_id = $ret['data'][0]['id'];
                   
                   if($done != 1)
                    {
                      $done = '0';   
                    }
                   
                   //setting question as done
                   $query = "UPDATE question_answer SET done='$done' WHERE user_id='$user_id' AND question_id='$q_id'";
                   $db->query($query);
                   
                   $ret_fin['ok'] = 1;
                   $ret_fin['msg'] = 'Set as done.'; 
                }
             }  
                      
        
      
        echo json_encode($ret_fin);
                 
        }  
   
         
    }


?>