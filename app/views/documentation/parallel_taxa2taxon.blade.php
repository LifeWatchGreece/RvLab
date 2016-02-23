%%%%%%%%%%%%%%%%%%%% Taxa2dist + Taxondive<br>
%%%%% This is a version that merges the two functions applying parallelization and optimization of calculations<br><br>

%%%%% To run<br><br>

mpiexec -np 2 ../../bin/Rscript taxa2dist_taxondive.r "aggGenera400.csv" "matrixGenera400_noHeader.csv" 1000000 "/home/patkos/R-3.1.1/Datasets"<br><br>

%%%%% Analysis of parameters<br><br>

mpiexec:<br>
-np: Number of processors<br>
../../bin/Rscript:This is where R-3.1.1 is<br>
taxa2dist_taxondive.r: Name of the current script<br>
"aggGenera400.csv":The name of the first csv file.<br>
"matrixGenera400_noHeader.csv":The name of the second file<br>
1000000:Available RAM in bytes<br>
"/home/patkos/R-3.1.1/Datasets":The directory (path) where the datasets are stored<br><br>

%%%%% Notes