<?php

/**
 * Provides some functionality useful to mobile version of R vLab
 *
 * @license MIT
 * @author Alexandros Gougousis
 */
class MobileController extends AuthController {
    
    /**
     * Provides the structure of job submission form for every function supported by R vLab
     * 
     * @param string $function The name of the function
     * @return JSON
     */
    public function forms($function){
        
        if(!in_array($function, array('anosim','anova','bict','bioenv','cca','convert2r','hclust','mantel','metamds','metamds_visual','pca','parallel_mantel','parallel_permanova','parallel_taxa2dist','parallel_postgres_taxa2dist','parallel_taxa2taxon','parallel_anosim','permanova','radfit','regression','second_metamds','simper','taxa2dist','taxondive','vegdist'))){
            $response = array(
                'message'   =>  'This function is not supported!'
            );
            return Response::json($response,404); 
        }
        
        $tooltips = Config::get('tooltips');
        
        $forms = array(
            'anosim' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data file from workspace files'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','standardize','pa','chi.square','horn','hellinger','log'),''),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true),
                    array('radio','box2','Select factor file:'),
                    array('columns','box2','column_select','Select Column in Factor File:',array(),'')
                ),
                'parameters'    =>  array(
                    array('text','permutations','Permutations:','999'),
                    array('dropdown','method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean')
                )
            ),
            'anova' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select envirmomental factor file data from loaded files'),                                        
                ),
                'parameters'    =>  array(
                    array('radio_pairs','one_or_two_way','Factor File - Fit an analysis of variance model by a call to lm for each stratum according to the following formulas',array('one'=>'One way Anova- aov.ex1<-aov(Factor1~Factor2, data)','two'=>'Two way Anova- aov.ex2<-aov(Factor1~Factor2*Factor3, data)'),'one'),                   
                    array('columns','box','Factor_select1','Select Column in Factor File (Factor1)',array(),''),
                    array('columns','box','Factor_select2','Select Column in Factor File (Factor2)',array(),''),
                    array('columns','box','Factor_select3','Select Column in Factor File <br>(Factor3 - optional for two way Anova)',array(),'')
                )
            ),
            'bict' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data file from workspace files'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),''),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true),
                    array('radio','box2','Select classification table with a row for each species or other basic taxon, and columns for identifiers of its classification at higher levels'),
                ),
                'parameters'    =>  array(
                    array('dropdown','species_family_select','Species or Family',array('species','family'),'species'),
                )
            ),
            'bioenv' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data as a symmetric square matrix from loaded files'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),''),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true),
                    array('radio','box2','Select enviromental variable factor file'),
                ),
                'parameters'    =>  array(
                    array('dropdown','method_select','Method',array('spearman','pearson','canberra'),'spearman'),
                    array('dropdown','index','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean'),
                    array('text','upto','upto','2'),
                    array('dropdown','trace','trace',array('FALSE','TRUE'),'FALSE')
                )
            ),
            'cca' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data file from loaded files'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),''),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true),
                    array('radio','box2','Select factor file'),                                       
                ),
                'parameters'    =>  array(
                    array('columns','box2','Factor_select1','Select Column in Factor File (Factor1)',array(),''),
                    array('columns','box2','Factor_select2','Select Column in Factor File (Factor2)',array(),''),
                    array('columns','box2','Factor_select3','Select Column in Factor File (Factor3)',array(),'')
                )
            ),
            'convert2r' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select data in standard csv format from loaded files')                                     
                ),
                'parameters'    =>  array(
                    array('label','Abunfance File',"Reshape data to create an abundance file according the following equation: geotransformed<-cast(geo, Header1~Header2, Function_to_run, value='Header3')"),
                    array('columns','box','header1_id','Header 1',array(),''),
                    array('columns','box','header2_id','Header 2',array(),''),
                    array('columns','box','header3_id','Header 3',array(),''),
                    array('dropdown','function_to_run','Function to run',array('sum','mean'),'sum'),
                    array('label','Factor File',"create a factor file by selecting from three availbale headers."),
                    array('columns','box','header1_fact','Factor Header 1',array(),''),
                    array('columns','box','header2_fact','Factor Header 2',array(),''),
                    array('columns','box','header3_fact','Factor Header 3',array(),'')
                )
            ),
            'hclust' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select a dissimilarity structure as produced by dist from loaded files'),
                    array('radio','box2','Select factor file (Optional)'),
                    array('columns','box2','column_select','Select Column in Factor File:',array(),'')
                ),
                'parameters'    =>  array(
                    array('dropdown','method_select','Method',array('ward.D','ward.D2','single','complete','average','mcquitty','median','centroid'),'ward.D')
                )
            ),
            'mantel' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select a dissimilarity structure as produced by dist from workspace files'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','standardize','pa','chi.square','horn','hellinger','log'),''),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true),
                    array('radio','box2','Select a dissimilarity structure as produced by dist'),                                       
                ),
                'parameters'    =>  array(
                    array('text','permutations','Permutations','999'),
                    array('dropdown','method_select','Method:',array('pearson','spearman','canberra'),'spearman')
                )
            ),
            'metamds' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data as a symmetric square matrix from loaded files'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','standardize','pa','chi.square','horn','hellinger','log'),''),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true),
                    array('radio','box2','Select factor file (Optional)'),
                    array('columns','box2','column_select','Select Column in Factor File:',array(),'')
                ),
                'parameters'    =>  array(
                    array('dropdown','method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean'),
                    array('text','k_select','K','12'),
                    array('text','trymax','trymax','20'),
                    array('dropdown','autotransform_select','autotransform',array('FALSE','TRUE'),'TRUE'),
                    array('text','noshare','noshare','0.1'),
                    array('dropdown','wascores_select','wascores',array('FALSE','TRUE'),'TRUE'),
                    array('dropdown','expand','expand',array('FALSE','TRUE'),'TRUE'),
                    array('text','trace','trace','1')
                )
            ),
            'metamds_visual' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data as a symmetric square matrix from loaded files'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),'none'),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true)
                ),
                'parameters'    =>  array(
                    array('text','top_species','Number of top ranked species','21'),
                    array('dropdown','method_select_viz','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean'),
                    array('text','k_select_viz','K','12'),
                    array('text','trymax_viz','trymax_viz','20'),                    
                )
            ),
            'parallel_anosim' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data file from loaded files'),                    
                    array('checkbox','transpose','Check to transpose matrix','transpose',false),
                    array('radio','box2','Select factor file')
                ),
                'parameters'    =>  array(
                    array('dropdown','method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean'),
                    array('text','column_select','Select Column in Factor File:','1'),
                    array('text','permutations','Permutations','999'),
                    array('dropdown','No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10'),'2')
                )
            ),
            'parallel_mantel' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select a dissimilarity structure as produced by dist from loaded files'),                    
                    array('checkbox','transpose1','Check to transpose matrix','transpose',false),
                    array('radio','box2','Select a dissimilarity structure as produced by dist'),
                    array('checkbox','transpose2','Check to transpose matrix','transpose',false)
                ),
                'parameters'    =>  array(
                    array('dropdown','method_select','Method:',array('pearson','spearman','canberra'),'spearman'),
                    array('text','permutations','Permutations','999'),
                    array('dropdown','No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10'),'2')
                )
            ),
            'parallel_permanova' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data file from loaded files'),                    
                    array('checkbox','transpose','Check to transpose matrix','1',true),
                    array('radio','box2','Select factor file')
                ),
                'parameters'    =>  array(
                    array('radio_pairs','single_or_multi',' ',array('single'=>'Single parameter - adon<-adonis(abundance_data~Factor1, ENV_data, permutations, distance)','multi'=>'Multiple parameter - adon<-adonis(abundance_data~Factor1*Factor2, ENV_data, permutations, distance)'),'single'),
                    array('text','column_select','Select Column in Factor File (Factor1)','1'),
                    array('text','column_select2','Select Column in Factor File (Factor2)','1'),
                    array('text','permutations','Permutations','999'),
                    array('dropdown','method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean'),                    
                    array('dropdown','No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10'),'2')
                )
            ),
            'parallel_postgres_taxa2dist' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select classification table with a row for each species or other basic taxon, and columns for identifiers of its classification at higher levels from loaded files'),
                ),
                'parameters'    =>  array(
                    array('dropdown','varstep','varstep',array('FALSE','TRUE'),'FALSE'),
                    array('dropdown','check_parallel_taxa2dist','check',array('FALSE','TRUE'),'TRUE'),
                    array('dropdown','No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10','11','12'),'2')
                )
            ),
            'parallel_taxa2dist' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select classification table with a row for each species or other basic taxon, and columns for identifiers of its classification at higher levels from loaded files'),
                ),
                'parameters'    =>  array(
                    array('dropdown','varstep','varstep',array('FALSE','TRUE'),'FALSE'),
                    array('dropdown','check_parallel_taxa2dist','check',array('FALSE','TRUE'),'TRUE'),
                    array('dropdown','No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10','11','12'),'2')
                )
            ),
            'parallel_taxa2taxon' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select classification table with a row for each species or other basic taxon, and columns for identifiers of its classification at higher levels from loaded files'),
                    array('radio','box2','Select community data matrix from loaded files'),
                    array('checkbox','transpose','Check to transpose matrix','transpose',false)
                ),
                'parameters'    =>  array(
                    array('dropdown','varstep','varstep',array('FALSE','TRUE'),'TRUE'),
                    array('dropdown','No_of_processors','Number of Processors',array('2','3','4','5','6','7','8','9','10'),'2')
                )
            ),
            'pca' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data as a symmetric square matrix from loaded files'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),''),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true),
                    array('radio','box2','Select factor file (Optional)'),
                    array('columns','box2','column_select','Select Column in Factor File:',array(),'')
                ),
                'parameters'    =>  array(                                 
                )
            ),
            'permanova' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data file from loaded files'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),''),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true),
                    array('radio','box2','Select factor file:')
                ),
                'parameters'    =>  array(
                    array('radio_pairs','single_or_multi',' ',array('single'=>'Single parameter - adon<-adonis(abundance_data~Factor1, ENV_data, permutations, distance)','multi'=>'Multiple parameter - adon<-adonis(abundance_data~Factor1*Factor2, ENV_data, permutations, distance)'),'single'), 
                    array('columns','box2','column_select','Select Column in Factor File (Factor1)',array(),''),
                    array('columns','box2','column_select2','Select Column in Factor File (Factor2)',array(),''),
                    array('text','permutations','Permutations:','999'),
                    array('dropdown','method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean')
                )
            ),
            'radfit' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data as a symmetric square matrix from loaded files'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),''),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true)                   
                ),
                'parameters'    =>  array(
                    array('text','column_radfit','Select Column from community data matrix:','0'),
                )
            ),
            'regression' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select enviromental factor file data from loaded files'), 
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),''),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true)   
                ),
                'parameters'    =>  array(
                    array('radio_pairs','single_or_multi','Factor File - lm is used to fit linear models. It can be used to carry out regression according to the following formulas: ',array('single'=>'Single linear regression - fit<-lm(Factor1~Factor2, data)','multi'=>'Multiple linear regression- fit2<-lm(Factor1~Factor2+Factor3, data)'),'single'),                   
                    array('columns','box','Factor_select1','Select Column in Factor File (Factor1)',array(),''),
                    array('columns','box','Factor_select2','Select Column in Factor File (Factor2)',array(),''),
                    array('columns','box','Factor_select3','Select Column in Factor File <br>(Factor3 - optional for multiple linear regression)',array(),'')
                )
            ),      
            'second_metamds' =>  array(
                'inputs' =>  array(
                    array('fileboxes','box[]','Select community data file(s) from loaded files'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),''),
                    array('checkbox','transpose','Check to transpose matrix','transpose',false)
                ),
                'parameters'    =>  array(
                    array('dropdown','method_select','Method:',array('euclidean','manhattan','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean'),
                    array('text','k_select','K','2'),
                    array('text','trymax','trymax','20'),
                    array('dropdown','autotransform_select','autotransform parameter:',array('FALSE','TRUE'),'TRUE'),
                    array('text','noshare','noshare','0.1'),
                    array('dropdown','wascores_select','wascores',array('FALSE','TRUE'),'TRUE'),
                    array('dropdown','expand','expand',array('FALSE','TRUE'),'TRUE'),
                    array('text','trace','trace','1'),
                )
            ),
            'simper' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data file from workspace files'),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true),
                    array('radio','box2','Select factor file'),
                    array('columns','box2','column_select','Select Column in Factor File:',array(),'')
                ),
                'parameters'    =>  array(        
                    array('text','permutations','Permutations:','0'),
                    array('dropdown','trace','Trace',array('FALSE','TRUE'),'FALSE')                    
                )
            ),
            'taxa2dist' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select classification table with a row for each species or other basic taxon, and columns for identifiers of its classification at higher levels from loaded files')
                ),
                'parameters'    =>  array(
                    array('dropdown','varstep','varstep',array('FALSE','TRUE'),'FALSE'),
                    array('dropdown','check_taxa2dist','check_taxa2dist',array('FALSE','TRUE'),'TRUE')
                )
            ),
            'taxondive' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data matrix from workspace files'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','standardize','pa','chi.square','horn','hellinger','log'),'none'),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true),
                    array('radio','box2','Select taxonomic distances among taxa for community data defined above (dist object)'),
                    array('radio','box3','Select factor file (Optional):'),
                    array('columns','box3','column_select','Select Column in Factor File:',array(),''),
                ),
                'parameters'    =>  array(
                    array('dropdown','match_force','match_force',array('FALSE','TRUE'),'FALSE'),
                    array('dropdown','deltalamda','Taxondive parameter:',array('Delta','Lamda'),'Delta')
                )
            ),
            'vegdist' =>  array(
                'inputs' =>  array(
                    array('radio','box','Select community data matrix from loaded files.'),
                    array('dropdown','transf_method_select','Select Transformation Method:',array('none','max','freq','normalize','range','pa','chi.square','horn','hellinger','log'),''),
                    array('checkbox','transpose','Check to transpose matrix','transpose',true)
                ),
                'parameters'    =>  array(
                    array('dropdown','method_select','Method:',array('euclidean','canberra','bray','kulczynski','jaccard','gower','morisita','horn','mountford','raup','binomial','chao'),'euclidean'),
                    array('dropdown','binary_select','Binary',array('FALSE','TRUE'),'FALSE'),
                    array('dropdown','diag_select','diag',array('FALSE','TRUE'),'FALSE'),
                    array('dropdown','upper_select','upper',array('FALSE','TRUE'),'FALSE'),
                    array('dropdown','na_select','na.rm',array('FALSE','TRUE'),'FALSE')
                )
            )
        );
        
        $response = array(
            'structure' =>  $forms[$function],
            'tooltips'  =>  $tooltips
        );
        
        return Response::json($response,200);
        
    }
    
}
