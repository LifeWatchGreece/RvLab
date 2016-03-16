# A Taxa2Dist implementation with Postgres and MPI
# Instead of calculating the huge matrix in RAM, we break it into 
# a number of submatrices, based on the available RAM we are given as input.
# Each processor is calculating a portion of the submatrix and saves it in the DB.
# We then restore the result table-by-table and save the taxdist in a csv file
# The program has been tested to provide correct results

# To run: mpiexec -np 2 ../../bin/Rscript taxa2distPostgresMPI.r "aggGenera400.csv" 1000000 "/home/patkos/R-3.1.1/Datasets" "/home/patkos/R-3.1.1/TeoScripts/outputs" "T_tmp" TRUE TRUE

# clean previous results
rm(list=ls())

# Initial Parameters given by the user
args <- commandArgs(trailingOnly = TRUE)
datasetName <- args[1] # The name of the csv file
MAXmem <- as.numeric(args[2]) # The size of RAM in bytes
datasetPath <- args[3] # The directory (path) where the dataset is stored
outputPath <- args[4] # The directory (path) where we wish the output CSV to be stored
keepResult <- FALSE #as.logical(args[5]) # If we wish 
tempTblName <- args[5] # The unique name of the temporary tables stored in postgres
varstep <- as.logical(args[6])
check <-as.logical(args[7])

# load libraries
library(dplyr)
library(RPostgreSQL)
library(pbdMPI, quiet = TRUE)

init()

# Start the clock
 fullProgramTimer =  proc.time()

# TP: Get the size of the cluster
processors <- comm.size()
myrank <- comm.rank()


# create pointers to DB
LwDB <- src_postgres(dbname = "", user = "", password = "", host = "")
drv <- dbDriver("PostgreSQL")
con <- dbConnect(drv, dbname = "", user = "", password = "", host = "")




# TP: The step will be the number of rows/columns assigned to each node
# TP: The rest are matrices that will be sent to each node
agg <- NULL
add <- NULL
out <- NULL
CHECK3 <- TRUE # Not enough RAM


# TP: Only the master node performs these tasks
if (myrank == 0) {

if(!dbExistsTable(con, datasetName)){
  # set path to datasets, if not in DB already
  setwd(datasetPath)
  
  #aggs contains the aggregation file
  agg <- read.table(datasetName, header = TRUE, sep=",")
  
} else {
  # bring the dataset from the DB to memory
  aggDB <- tbl(LwDB, datasetName)
  agg <- as.data.frame(aggDB)
}



######## Inside Taxa2Dist
# These steps are the initialization operations of Taxa2Dist. Nothing changes here
#varstep <- TRUE
#check <- TRUE

rich <- apply(agg, 2, function(taxa) length(unique(taxa)))
S <- nrow(agg)
if (check) {
  keep <- rich < S & rich > 1
  rich <- rich[keep]
  agg <- agg[, keep]
}
i <- rev(order(rich))
agg <- agg[, i]
rich <- rich[i]
if (varstep) {
  add <- -diff(c(nrow(agg), rich, 1))
  add <- add/c(S, rich)
  add <- add/sum(add) * 100
}
else {
  add <- rep(100/(ncol(agg) + check), ncol(agg) + check)
}
if (!is.null(names(add))) 
  names(add) <- c("Base", names(add)[-length(add)])
if (!check) 
  add <- c(0, add)
  
# TP: Only matrices can be sent through pbdMPI so we transform 'add' to matrix
add <- matrix(add)

}

# TP: Broadcast the two matrices to all CPUs
agg.all <- bcast(agg, rank.source = 0)
add.all <- bcast(add, rank.source = 0)

### We first check if we have enough RAM to execute the actual vegan function
# N is the number of rows of our dataset
N <- nrow(agg.all)
# The number of elements that will be created by the outer product
elements <- N*N
# The total number of elements a matrix can have that fit in RAM
maxElements <- floor(((MAXmem-200)/8 ) /3 )

# We need to break the NxN matrix into submatrices, having m<=N rows and N columns
# As a result, the submatrix should have m = maxElements/N rows
m <- floor (maxElements/N )
if (m > N) {m <- N}



if (m < 1) {
	comm.print("ERROR! RAM IS NOT ENOUGH, INCREASE AVAILABLE RAM")
	comm.print("The minimum matrix size that is created due to an outer product cannot fit in available RAM.")
	m <-1
	CHECK3 <- FALSE
}

if (m < processors) {
	comm.print("ERROR! PARALLELIZATION NOT POSSIBLE, EITHER INCREASE RAM OR REDUCE THE NUMBER OF CPUs")
	CHECK3 <- FALSE
}




# The number of submamtrices
numOfMatrices <- ceiling(N/m) 

#comm.print("Number of submatrices that will be needed:")
#comm.print( numOfMatrices)

# This is the number of rows in each submatrix that a processor is assigned to
step <- ceiling(m/processors)

if (CHECK3) {

if (numOfMatrices > 1) {
for (k in 1:(numOfMatrices-1)) {


# TP: Now, each node has a different set of data to work with
start <- ((k-1)*m) + myrank*step+1
end <- start+step-1
# TP: The last node has less elements that the rest, i.e., the remaining elements
if (myrank == processors-1) {end<-k*m}

#comm.print(object.size(agg.all), all.rank = TRUE)
#comm.print(start, all.rank = TRUE)
#comm.print(end, all.rank = TRUE)

#out <- matrix(add.all[1], nrow(agg.all), (end-start+1))
out <- matrix(add.all[1], (end-start+1), nrow(agg.all))

# TP: This is the main loop that is performed on each processor separately
for (i in 1:ncol(agg.all)) {
  out <- out + add.all[i + 1] * outer(agg.all[start:end, i], agg.all[, i], "!=")
}


  # Save it to the DB
  dbWriteTable(con, paste(tempTblName,k,"P",myrank), as.data.frame(as.vector(out)), append = F)

 
  # Continue with the rest of the rows, therefore reinitialize the matrix
  rm(out)
  out <- matrix(add.all[1], (end-start+1), nrow(agg.all))

} # end for k

} # end if

### Now the last matrix


######################################3

  # Do the same for the last matrix that may have less than m rows
    CHECK2 <- TRUE # It is FALSE if the last processor has no elements to process
  doneRows <- (numOfMatrices-1)*m
  m <- N-((numOfMatrices-1)*m)
  step <- ceiling(m/processors)
  start <- doneRows + myrank*step+1
  end <- start+step-1
  # TP: The last node has less elements that the rest, i.e., the remaining elements
  if (myrank == processors-1) {
	end<- N
	if (start>N) {
		start <- N
		CHECK2 <- FALSE	
		}
	}
  rm(out)
  
  
  out <- matrix(add.all[1], (end-start+1), nrow(agg.all))
  for (i in 1:ncol(agg.all)) {
    out <- out + add.all[i + 1] * outer(agg.all[start:end, i], agg.all[, i], "!=")
  }

#comm.print(dim(out), all.rank = TRUE)

  # Save it to the DB
  if (myrank != processors-1 || CHECK2)
    dbWriteTable(con, paste(tempTblName,numOfMatrices,"P",myrank), as.data.frame(as.vector(out)), append = F)
	
 
  rm(out)
  
  ## Now, all our data is stored in Postgres in consecutive tables

  ######################################3

  
  ####### Write output to a CSV file
  ## 
  if (!keepResult && myrank == 0) {

  setwd(outputPath)

  for (k in 1:numOfMatrices) {
    for (proc in 0:(processors-1)) {
  
  # Take a reference to the table
  outDB <- tbl(LwDB, paste(tempTblName,k,"P",proc))
  
  #dim(outDB)

  outputFrame <- head(outDB, n=nrow(outDB))
  colnames(outputFrame) <- c("index", "values")
  
  attach(outputFrame)
  setOfRows <- nrow(outDB)/N
  output <- matrix(values, setOfRows,N)
  detach(outputFrame)
  
  outputfileName <- paste(datasetName,"taxa2dist_output.csv")
  
  if (k>1 || proc >0) {
   write.table(output, file=outputfileName, row.names=FALSE, col.names=FALSE, , sep=",", append = TRUE)
   #write.table(output, file="taxa2DistPostgres.csv", row.names=FALSE, col.names=FALSE, , sep=",", append = TRUE)
  } else {
     write.table(output, file=outputfileName, row.names=FALSE, col.names=FALSE, , sep=",")  
    #write.table(output, file="taxa2DistPostgres.csv", row.names=FALSE, col.names=FALSE, , sep=",")  
  }
  
  
  #output[upper.tri(output, diag = T)] <- 0
  #output <- as.dist(output)
  #output <- as.matrix(output)
 

  ##object.size(output)

     dbRemoveTable(con, paste(tempTblName,k,"P",proc))
	 rm(outDB)
	 rm(output)
	 rm(outputFrame)
	 
  } # end for proc
  
  } # end for numOfMatrices
  
  } # end if myrank
  
  
	output <- c(N, numOfMatrices, processors, tempTblName)
    comm.print("Below are the size of the distance matrix, the num of submatrices created, the processors used and the submatrices name", rank.print = 0)
    comm.print(output, rank.print = 0)

	
	}
	
# Stop the clock
 comm.print("Full program execution time for each processor", rank.source = 0)
 comm.print(proc.time() - fullProgramTimer, all.rank = T)
	

## Closes the connection
dbDisconnect(con)

finalize()



 # For verification purposes, to show that the results are correct
  #################################
  #library(vegan)
  #setwd("/home/patkos/R-3.1.1/Datasets")
  #agg <- read.table(datasetNameInDB, header = TRUE, sep=",")
  #taxdis <- taxa2dist(agg, varstep=TRUE)
  #write.table(as.matrix(taxdis), file="/home/patkos/R-3.1.1/TeoScripts/taxdis.csv", row.names=FALSE, col.names=FALSE, , sep=",")  

  #setwd("/home/patkos/R-3.1.1/TeoScripts")
  #taxdisCustom <- read.table("outDB.csv", header = FALSE, sep=",")
  #taxdisCustom[row(taxdisCustom) == col(taxdisCustom)] <- 0
  
  #dim(taxdisCustom)
  #comparison <- as.matrix(taxdis) - taxdisCustom
  #write.table(comparison, file="/home/patkos/R-3.1.1/TeoScripts/comparison.csv", row.names=FALSE, col.names=FALSE, , sep=",")  

  #all.equal(as.matrix(taxdis), as.matrix(as.dist(taxdisCustom)))
