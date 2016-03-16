<?php $function = "permanova"; ?>

{{ Form::open(array('url'=>'job','class'=>'form-horizontal','id'=>'permanova_form','style'=>'display:none')) }}        

{{ form_function_about('permanova',$tooltips) }}
<br>
<div style="color: blue; font-weight: bold">Input files</div>

    {{ form_radio_files('permanova-box','Select community data file from loaded files',$tooltips,$workspace_files) }}
    {{ form_dropdown('permanova-transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'',$tooltips) }}                                 
    {{ form_checkbox('taxondive-transpose','Check to transpose matrix','transpose',true,$tooltips) }}   
    {{ form_radio_files('permanova-box2','Select factor file',$tooltips,$workspace_files) }}     
    
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
    
    {{ form_dropdown('permanova-column_select','Select Column in Factor File (Factor1)',array(),'',$tooltips) }}
    {{ form_dropdown('permanova-column_select2','Select Column in Factor File (Factor2)',array(),'',$tooltips) }}
    
    {{ form_textinput('permanova-permutations','Permutations','999',$tooltips) }}    
    {{ form_dropdown('permanova-method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean',$tooltips) }}     
    
    <input type="hidden" name="function" value="{{ $function }}">
    <div style='text-align: center'>
        <button class="btn btn-sm btn-primary">Run Function</button>
    </div>

{{ Form::close() }}

<script type="text/javascript">        
    
    $(document).on('change', '#permanova_form input[name="box2"]', function(){
        var selectedValue = $("#permanova_form input[name='box2']:checked").val();
        // If user selects another file call the function that updates the dropdowns
        loadCsvHeaders(selectedValue,"permanova_form","column_select");     
        loadCsvHeaders(selectedValue,"permanova_form","column_select2");
    });
    
</script>                    

<?
    // Initially, we assume that the first in row file is selected and that file will be used to retrieve the headers
    $count = 0;
    foreach($workspace_files as $file){
        if($count == 0){
            echo "<script type='text/javascript'>loadCsvHeaders('".$file->filename."','permanova_form','column_select');</script>";
            echo "<script type='text/javascript'>loadCsvHeaders('".$file->filename."','permanova_form','column_select2');</script>";
        }
        $count++;
    }        
?>