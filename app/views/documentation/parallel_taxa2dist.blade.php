%%%%%%%%%%%%%%%%%%%% Taxa2Dist for BIG Datasets
<br><br>
%%%%% To run
<br><br>
mpiexec -np 2 ../../bin/Rscript taxa2distPostgresMPIv2.r "aggGenera400.csv" 1000000 "/home/patkos/R-3.1.1/Datasets" "/home/patkos/R-3.1.1/TeoScripts/outputs" "T_tmp"
<br><br>
%%%%% Analysis of parameters
<br><br>
mpiexec: 				<do not change><br>
-np: 					<do not change><br>
2: 					Number of processors<br>
../../bin/Rscript:			This is where R-3.1.1 is<br>
taxa2distPostgresMPIv2.r: 		Name of the current script<br>
"aggGenera400.csv": 			This is the name of the INPUT csv file<br>
1000000: 				This is the size of RAM available (in bytes). Please, adjust<br>
"/home/patkos/R-3.1.1/Datasets":	This is where the INPUT dataset can be found<br>
"/home/patkos/R-3.1.1/TeoScripts/outputs": This is where the OUTPUT csv file will be stored<br>
"T_tmp": 				This is a unique ID to be used for postqresql<br>
<br><br>
%%%%% Notes
<br><br>
The output is a CSV square matrix, NOT a distance matrix. If you want to use this with taxondive, you have to convert it first:
tax <- read.table("taxa2DistPostgres.csv", header = FALSE, sep = ",")
taxdis <-as.dist(tax)
<br><br>
IMPORTANT: IT TAKES A LOT OF TIME TO RUN. This is because it creates many small matrices in Postgresql. To improve, adjust the RAM size and the number of processors. For small datasets, use taxa2distMPI.r instead!