<?php $function = "parallel_permanova"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'parallel_permanova_form','style'=>'display:none')) }}        

{{ form_function_about('parallel_permanova',$tooltips) }}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('parallel_permanova-box','Select community data file from loaded files',$tooltips,$workspace_files) }}             
    {{ form_checkbox('parallel_permanova-transpose','Check to transpose matrix','1',true,$tooltips) }}
    {{ form_radio_files('parallel_permanova-box2','Select factor file',$tooltips,$workspace_files) }}              
    
    <div style="color: blue; font-weight: bold">Parameters</div>
    
    <div class='radio_wrapper'>        
        <div class="radio">
            <label>
              <input type="radio" name="single_or_multi" value="single" checked>
              Single parameter - adon<-adonis(abundance_data~Factor1, ENV_data, permutations, distance)
            </label>
        </div>
        <div class="radio">
            <label>
              <input type="radio" name="single_or_multi" value="multi">
              Multiple parameter - adon<-adonis(abundance_data~Factor1*Factor2, ENV_data, permutations, distance)
            </label>
        </div>
    </div>   
    
    {{ form_textinput('parallel_permanova-column_select','Select Column in Factor File (Factor1)','1',$tooltips) }}
    {{ form_textinput('parallel_permanova-column_select2','Select Column in Factor File (Factor2)','1',$tooltips) }}
    {{ form_textinput('parallel_permanova-permutations','Permutations','999',$tooltips) }}    
    {{ form_dropdown('parallel_permanova-method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean',$tooltips) }}     
    {{ form_dropdown('parallel_permanova-No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10'),'2',$tooltips) }}            
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}