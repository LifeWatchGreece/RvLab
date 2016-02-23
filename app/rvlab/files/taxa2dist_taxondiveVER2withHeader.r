# A Taxa2Dist implementation with Postgres and MPI
# Instead of calculating the huge matrix in RAM, we break it into 
# a number of submatrices, based on the available RAM we are given as input.
# Each processor is calculating a portion of the submatrix and saves it in the DB.
# If needed, we restore the result table-by-table and save the taxdist in a csv file
# The program has been tested to provide correct results

# To run: mpiexec -np 2 ../../bin/Rscript taxa2dist_taxondiveVER2withHeader.r "aggGenera400.csv" "matrixGenera400_withHeader.csv" 1000000 "/home/patkos/R-3.1.1/Datasets" 
#	      mpiexec -np 2 ../../bin/Rscript taxa2dist_taxondiveVER2withHeader.r "aggSpecies_1Percent.csv" "matrixSpecies_noHeader_1Percent.csv" 1000000 "/home/patkos/R-3.1.1/Datasets"
#         mpiexec -np 2 ../../bin/Rscript taxa2dist_taxondiveVER2withHeader.r "softlagoonaggregation.csv" "softlagoonAbundance.csv" 1000000 "/home/patkos/R-3.1.1/Datasets"


# clean previous results
rm(list=ls())

# Initial Parameters given by the user
args <- commandArgs(trailingOnly = TRUE)
datasetName <- args[1] # The name of the csv file
datasetName2 <- args[2] # The name of the csv file
MAXmem <- as.numeric(args[3]) # The size of RAM in bytes
datasetPath <- args[4] # The directory (path) where the dataset is stored

# load libraries
#library(dplyr)
#library(RPostgreSQL)
library(pbdMPI, quiet = TRUE)

init()

# Start the clock
 fullProgramTimer =  proc.time()


# TP: Get the size of the cluster
processors <- comm.size()
myrank <- comm.rank()


# create pointers to DB
#LwDB <- src_postgres(dbname = "rpsqldb", user = "rpsql", password = "Rp0$+l3m3")

#drv <- dbDriver("PostgreSQL")
#con <- dbConnect(drv, dbname = "rpsqldb", user = "rpsql", password = "Rp0$+l3m3")




# TP: The step will be the number of rows/columns assigned to each node
# TP: The rest are matrices that will be sent to each node
agg <- NULL
add <- NULL
out <- NULL
comm3 <- NULL
CHECK <- TRUE


# TP: Only the master node performs these tasks
if (myrank == 0) {

  # set path to datasets, if not in DB already
  setwd(datasetPath)
  
  #aggs contains the aggregation file
  agg <- read.table(datasetName, header = TRUE, sep=",")
  
  comm2<- read.table(datasetName2, header = TRUE, sep=",")
  
# transpose only the numerical parts, leaving the first column as it is
comm3<-t(comm2[,-1])
#convert it into a data frame
comm3<-data.frame(comm3)
#assign column names to the new data frame by taking the first column from the original data frame 
names(comm3) <- comm2[,1] 


######## Inside Taxa2Dist
# These steps are the initialization operations of Taxa2Dist. Nothing changes here
varstep <- TRUE
check <- TRUE

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




# TAXONDIVE code
comm3 <- as.matrix(comm3)
#del <- dstar <- dplus <- Ed <- Edstar <- edplus <- NULL



################################
  
#  runningRow <- 1
#  delsum <- numeric(length = nrow(comm3))
#  dstarsum <- numeric(length = nrow(comm3))
#  Lambda <- numeric(length = nrow(comm3))
#  dissum <- 0
#  dissumSqr <- 0
#  varomebarSum <- 0
#  tmp <- 0

#  cs <- colSums(comm3)

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
	CHECK <- FALSE
}

if (m < processors) {
	comm.print("ERROR! PARALLELIZATION NOT POSSIBLE, EITHER INCREASE RAM OR REDUCE THE NUMBER OF CPUs")
	CHECK <- FALSE
}


# The number of submamtrices
numOfMatrices <- ceiling(N/m) 

comm.print("Number of submatrices that will be needed:")
comm.print( numOfMatrices)

# This is the number of rows in each submatrix that a processor is assigned to
step <- ceiling(m/processors)


########################################################################
########################################################################
comm3.all <- bcast(comm3, rank.source = 0)
delsumV2 <- numeric(length = nrow(comm3.all))
dstarsumV2 <- numeric(length = nrow(comm3.all))
LambdaV2 <- numeric(length = nrow(comm3.all))
tmpV2 <- 0
dissumV2 <- 0
dissumSqrV2 <- 0
varomebarSumV2 <- 0


csV2 <- colSums(comm3.all)

if (CHECK) {

if (numOfMatrices > 1) {
for (k in 1:(numOfMatrices-1)) {


# TP: Now, each node has a different set of data to work with
start <- ((k-1)*m) + myrank*step+1
end <- start+step-1
# TP: The last node has less elements that the rest, i.e., the remaining elements
if (myrank == processors-1) {end<-k*m}


#comm.print("TEST TEST")

#comm.print(object.size(agg.all), all.rank = TRUE)
#comm.print(start, all.rank = TRUE)
#comm.print(end, all.rank = TRUE)

#comm.print( nrow(agg.all))


#out <- matrix(add.all[1], nrow(agg.all), (end-start+1))
out <- matrix(add.all[1], (end-start+1), nrow(agg.all))




# TP: This is the main loop that is performed on each processor separately
for (i in 1:ncol(agg.all)) {
  out <- out + add.all[i + 1] * outer(agg.all[start:end, i], agg.all[, i], "!=")
}


########################################################################
########################################################################

  for(comm3Rows in 1:nrow(comm3.all) ) {
    x <- comm3.all[comm3Rows,] 
	
	  # Calculate the portion of the outer that is equal to that of the outPartiallyComplete retrieved  
      outr.all <- outer(x[start:end], x)
      product <- outr.all*out
	  delsumV2temp1 <- sum(product[row(product)>(col(product)-(start-1))])
	  delsumV2temp2 <- reduce(delsumV2temp1, op = "sum")

	  dstarV2temp1 <- sum(outr.all[row(outr.all)>(col(outr.all)-(start-1))])
	  dstarV2temp2 <- reduce(dstarV2temp1, op = "sum")

      product <- product*out
	  LambdaV2temp1 <- sum(product[row(product)>(col(product)-(start-1))])
	  LambdaV2temp2 <- reduce(LambdaV2temp1, op = "sum")
	  
	  if (myrank == 0) {
		delsumV2[comm3Rows] <- delsumV2[comm3Rows] + delsumV2temp2
		dstarsumV2[comm3Rows] <- dstarsumV2[comm3Rows] +dstarV2temp2
		LambdaV2[comm3Rows] <- LambdaV2[comm3Rows] + LambdaV2temp2
		}
}

  tmpCSV2 <- outer(csV2[start:end], csV2)
  productCSV2 <- tmpCSV2 * out
  tmpV2temp1 <- sum(productCSV2[row(productCSV2)>(col(productCSV2)-(start-1))])
  tmpV2temp2 <- reduce(tmpV2temp1, op = "sum")
  
  dissumV2temp1 <- sum(out[row(out)>(col(out)-(start-1))])
  dissumV2temp2 <- reduce(dissumV2temp1, op = "sum")

  dissumSqrV2temp1 <- sum(out[row(out)>(col(out)-(start-1))]^2)
  dissumSqrV2temp2 <- reduce(dissumSqrV2temp1, op = "sum")

  varomebarSumV2temp1 <- sum((rowSums(out)-out[1,start])^2)/((N-1)^2)
  varomebarSumV2temp2 <- reduce(varomebarSumV2temp1, op = "sum")

  
  	  if (myrank == 0) {
		tmpV2 <- tmpV2 + tmpV2temp2
		dissumV2 <- dissumV2 + dissumV2temp2
		dissumSqrV2 <- dissumSqrV2 + dissumSqrV2temp2
		varomebarSumV2 <- varomebarSumV2 + varomebarSumV2temp2
	  }


	 
	  






  # Save it to the DB
  #dbWriteTable(con, paste(tempTblName,k,"P",myrank), as.data.frame(as.vector(out)), append = F)

 
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


#comm.print("TEST TEST")
#comm.print(start, all.rank = TRUE)
#comm.print(end, all.rank = TRUE)
#comm.print(step, all.rank = TRUE)



  out <- matrix(add.all[1], (end-start+1), nrow(agg.all))
  for (i in 1:ncol(agg.all)) {
    out <- out + add.all[i + 1] * outer(agg.all[start:end, i], agg.all[, i], "!=")
  }
  
  
   for(comm3Rows in 1:nrow(comm3.all) ) {
    x <- comm3.all[comm3Rows,] 
	
	  # Calculate the portion of the outer that is equal to that of the outPartiallyComplete retrieved  
      outr.all <- outer(x[start:end], x)
      product <- outr.all*out
	  delsumV2temp1 <- sum(product[row(product)>(col(product)-(start-1))])
	  if (myrank == processors-1 && !CHECK2) { delsumV2temp1 <-0 }
	  delsumV2temp2 <- reduce(delsumV2temp1, op = "sum")

	  dstarV2temp1 <- sum(outr.all[row(outr.all)>(col(outr.all)-(start-1))])
	  if (myrank == processors-1 && !CHECK2) { dstarV2temp1 <- 0 }
	  dstarV2temp2 <- reduce(dstarV2temp1, op = "sum")

      product <- product*out
	  LambdaV2temp1 <- sum(product[row(product)>(col(product)-(start-1))])
	  	  if (myrank == processors-1 && !CHECK2) { LambdaV2temp1 <- 0 }
	  LambdaV2temp2 <- reduce(LambdaV2temp1, op = "sum")
	  
	  if (myrank == 0) {
		delsumV2[comm3Rows] <- delsumV2[comm3Rows] + delsumV2temp2
		dstarsumV2[comm3Rows] <- dstarsumV2[comm3Rows] +dstarV2temp2
		LambdaV2[comm3Rows] <- LambdaV2[comm3Rows] + LambdaV2temp2
		}


}

  tmpCSV2 <- outer(csV2[start:end], csV2)
  productCSV2 <- tmpCSV2 * out
  tmpV2temp1 <- sum(productCSV2[row(productCSV2)>(col(productCSV2)-(start-1))])
  if (myrank == processors-1 && !CHECK2) { tmpV2temp1 <- 0 }
  tmpV2temp2 <- reduce(tmpV2temp1, op = "sum")
  
  dissumV2temp1 <- sum(out[row(out)>(col(out)-(start-1))])
    if (myrank == processors-1 && !CHECK2) { dissumV2temp1 <- 0 }
  dissumV2temp2 <- reduce(dissumV2temp1, op = "sum")

  dissumSqrV2temp1 <- sum(out[row(out)>(col(out)-(start-1))]^2)
    if (myrank == processors-1 && !CHECK2) { dissumSqrV2temp1 <- 0 }
  dissumSqrV2temp2 <- reduce(dissumSqrV2temp1, op = "sum")

  varomebarSumV2temp1 <- sum((rowSums(out)-out[1,start])^2)/((N-1)^2)
    if (myrank == processors-1 && !CHECK2) { varomebarSumV2temp1 <- 0 }
  varomebarSumV2temp2 <- reduce(varomebarSumV2temp1, op = "sum")

  
  	  if (myrank == 0) {
		tmpV2 <- tmpV2 + tmpV2temp2
		dissumV2 <- dissumV2 + dissumV2temp2
		dissumSqrV2 <- dissumSqrV2 + dissumSqrV2temp2
		varomebarSumV2 <- varomebarSumV2 + varomebarSumV2temp2
	  }




  

#####################







#comm.print(dim(out), all.rank = TRUE)

  # Save it to the DB
    #dbWriteTable(con, paste(tempTblName,numOfMatrices,"P",myrank), as.data.frame(as.vector(out)), append = F)
	
 
  rm(out)
  

  ######################################3

 
	#output <- c(N, numOfMatrices, processors)
    #comm.print("Below are the size of the distance matrix, the num of submatrices created and the processors used ", rank.print = 0)
    #comm.print(output, rank.print = 0)
   
   
   
   if (myrank == 0) {
  comm3 <- ifelse(comm3 > 0, 1, 0)

   
  rs <- rowSums(comm3)
  #del <- delsum/rs/(rs - 1) * 2 
  delV2 <- delsumV2/rs/(rs - 1) * 2 
  #dstar <- delsum/dstarsum
  dstarV2 <- delsumV2/dstarsumV2
  m <-rowSums(comm3)
  dplus <- delsumV2/m/(m-1)*2
  #Lambda <- Lambda/m/(m-1)*2-dplus^2
  LambdaV2 <- LambdaV2/m/(m-1)*2-dplus^2
  #Ed <- tmp/sum(cs)/sum(cs - 1) * 2
  EdV2 <- tmpV2/sum(csV2)/sum(csV2 - 1) * 2
  #Edstar <- tmp/sum(cs)/(sum(cs) - 1) * 2
  EdstarV2 <- tmpV2/sum(csV2)/(sum(csV2) - 1) * 2
  
  #omebar <-dissum/N/(N-1)*2
  omebarV2 <-dissumV2/N/(N-1)*2
  
  #varome <- dissumSqr/N/(N-1)*2 - omebar^2
  varomeV2 <- dissumSqrV2/N/(N-1)*2 - omebarV2^2
	
  #varomebar <- varomebarSum/N - omebar^2
  varomebarV2 <- varomebarSumV2/N - omebarV2^2
	
  #vardplus <- 2 * (N - m)/(m * (m - 1) * (N - 2) * (N - 3)) * 
  #  ((N - m - 1) * varome + 2 * (N - 1) * (m - 2) * varomebar)

  vardplusV2 <- 2 * (N - m)/(m * (m - 1) * (N - 2) * (N - 3)) * 
    ((N - m - 1) * varomeV2 + 2 * (N - 1) * (m - 2) * varomebarV2)
	
	
  print("Species")
  print(m)
  print("D")
  print(delV2)  
  print("Dstar")
  print(dstarV2)
  print("Lambda")
  print(LambdaV2)
  print("Dplus")
  print(dplus)
  print("sd.Dplus")
  print(sqrt(vardplusV2))
  print("SDplus")
  print(m*dplus)
  print("ED")
  print(EdV2)
  print("EDstar")
  print(EdstarV2)
  print("EDplus")
  print(omebarV2)
   
   
  Taxondive.out <- list(Species = m, D = delV2, Dstar = dstarV2, Lambda = LambdaV2, 
                        Dplus = dplus, sd.Dplus = sqrt(vardplusV2), SDplus = m*dplus,
   ED = EdV2, EDstar = EdstarV2, EDplus = omebarV2)
  class(Taxondive.out) <- "taxondive.out"
   
   }
   
   
}
## Closes the connection
#dbDisconnect(con)

# Stop the clock
 comm.print("Full program execution time for each processor", rank.source = 0)
 comm.print(proc.time() - fullProgramTimer, all.rank = T)


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
