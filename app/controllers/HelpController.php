<?php

/**
 * Implements functionality related to R vLab documentation.
 *
 * @license MIT
 * @author Alexandros Gougousis
 */
class HelpController extends AuthController {

    public function __construct() {
        parent::__construct();               
    }       
    
    /**
     * Displays information about R vLab usage policy.
     * 
     * @return View|JSON
     */
    public function policy(){
        $job_max_storagetime = $this->system_settings['job_max_storagetime'];
        $rvlab_storage_limit = $this->system_settings['rvlab_storage_limit'];
        $max_users_suported = $this->system_settings['max_users_suported'];
        
        $data = array(
            'job_max_storagetime'  =>  $job_max_storagetime,
            'rvlab_storage_limit'  =>  $rvlab_storage_limit,
            'max_users_suported'  =>  $max_users_suported
        );
        
        if($this->is_mobile){                  
            return Response::json($data,200);
        } else {
            return $this->load_view('policy','Storage Policy',$data);
        }   
    }
    
    /**
     * Displays a list of links with scientific documentation for vLab functions used by R vLab.
     * 
     * @return View
     */
    public function technical_docs(){
        $links = array(
            'taxa2dist' => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/taxondive.html',
            'taxondive' => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/taxondive.html',
            'vegdist'   => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/vegdist.html',
            'hclust'    => 'https://stat.ethz.ch/R-manual/R-patched/library/stats/html/hclust.html',
            'bict'      => 'http://en.wikipedia.org/wiki/Diversity_index',
            'pca'       => 'https://stat.ethz.ch/R-manual/R-patched/library/stats/html/princomp.html',
            'cca'       => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/cca.html',
            'anosim'    => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/anosim.html',
            'anova'     => 'https://stat.ethz.ch/R-manual/R-devel/library/stats/html/aov.html',
            'permanova' => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/adonis.html',
            'mantel'    => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/mantel.html',
            'metamds'   => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/metaMDS.html',
            'second_metamds' => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/metaMDS.html',
            'metamds_visual' => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/metaMDS.html',
            'radfit'    => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/radfit.html',
            'bioenv'    => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/bioenv.html',
            'simper'    =>  'http://finzi.psych.upenn.edu/library/vegan/html/simper.html',
            'regression'=> 'https://stat.ethz.ch/R-manual/R-patched/library/stats/html/lm.html',
            'parallel_taxa2dist'=> "{{ url('help/documentation/parallel_taxa2dist') }}",
            'parallel_anosim'   => "http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/anosim.html",
            'parallel_mantel'   => "http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/mantel.html",
            'parallel_taxa2taxon' => "{{ url('help/documentation/parallel_taxa2taxon') }}",
            'parallel_permanova' => 'http://cc.oulu.fi/~jarioksa/softhelp/vegan/html/adonis.html',
            'convert2r' =>  'http://exposurescience.org/heR.doc/library/reshape/html/cast-9g.html'
        );
        
        $data['links'] = $links;
        return $this->load_view('technical_docs','Technical Documentation',$data);
        
    }
    
    /**
     * Displays an R vLab user guide.
     * 
     * @param string $function
     * @return View
     */
    public function documentation($function){

        return $this->load_view('documentation.'.$function,$function.' documentation');

    }

}
