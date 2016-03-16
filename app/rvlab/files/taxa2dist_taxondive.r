# A Taxa2Dist implementation with Postgres and MPI
# Instead of calculating the huge matrix in RAM, we break it into 
# a number of submatrices, based on the available RAM we are given as input.
# Each processor is calculating a portion of the submatrix and saves it in the DB.
# If needed, we restore the result table-by-table and save the taxdist in a csv file
# The program has been tested to provide correct results

# To run: mpiexec -np 2 ../../bin/Rscript taxa2dist_taxondiveVER2.r "aggGenera400.csv" TRUE "matrixGenera400_noHeader.csv" FALSE 1000000 "/home/patkos/R-3.1.1/Datasets" FALSE 

#mpiexec -np 2 ../../bin/Rscript  taxa2dist_taxondive.r "aggSpecies_1PercentJuly15.csv" TRUE "matrixSpecies_noHeader_1PercentJuly15.csv" FALSE 39728447488 "/home/patkos/R-3.1.1/ExperimentsJuly15/Datasets" FALSE
#mpiexec -np 2 ../../bin/Rscript  taxa2dist_taxondive.r "softlagoonaggregation.csv" TRUE "softlagoonAbundance.csv" TRUE 39728447488 "/home/patkos/R-3.1.1/ExperimentsJuly15/Datasets" TRUE
# mpiexec -np 2 ../../bin/Rscript  taxa2dist_taxondive.r "duneTax.csv" TRUE "dune.csv" TRUE 39728447488 "/home/patkos/R-3.1.1/ExperimentsJuly15/Datasets" FALSE

# clean previous results
rm(list=ls())

# Initial Parameters given by the user
args <- commandArgs(trailingOnly = TRUE)
datasetName <- args[1] # The name of the csv file
aggFileHeader <- as.logical(args[2]) 
datasetName2 <- args[3] # The name of the csv file
generaFileHeader <- as.logical(args[4]) # 
MAXmem <- as.numeric(args[5]) # The size of RAM in bytes
datasetPath <- args[6] # The directory (path) where the dataset is stored
varstep <- as.logical(args[7])


# load libraries
#library(dplyr)
#library(RPostgreSQL)
library(pbdMPI, quiet = TRUE)
library(vegan)
init()



# Start the clock
 fullProgramTimer =  proc.time()


# TP: Get the size of the cluster
processors <- comm.size()
myrank <- comm.rank()



# create pointers to DB
#LwDB <- src_postgres(dbname = "", user = "", password = "")

#drv <- dbDriver("PostgreSQL")
#con <- dbConnect(drv, dbname = "", user = "", password = "")




# TP: The step will be the number of rows/columns assigned to each node
# TP: The rest are matrices that will be sent to each node
agg <- NULL
add <- NULL
out <- NULL
comm3 <- NULL
comm3Binary <- NULL
CHECK <- TRUE


# TP: Only the master node performs these tasks
if (myrank == 0) {

  # set path to datasets, if not in DB already
  setwd(datasetPath)
  
  #aggs contains the aggregation file
  agg <- read.table(datasetName, header = aggFileHeader, sep=",")
  #agg <- read.table(datasetName, header = aggFileHeader, sep=",", row.names=1)
  
  print(">> LW RvLAB: Dimensions of Species classification table:")
  print(datasetName)
  print(dim(agg))
   
  
 # comm2<- read.table(datasetName2, header = generaFileHeader, sep=",")
  comm2<- read.table(datasetName2, header = generaFileHeader, sep=",")
  print(">> LW RvLAB: Dimensions of community data table:")
  print(datasetName2)
  print(dim(comm2))
 

  if (dim(agg)[1] == dim(comm2)[1]){
	# transpose only the numerical parts, leaving the first column as it is
	comm3<-t(comm2[,-1])
	comm3<-data.frame(comm3)
	names(comm3) <- comm2[,1]
	
	print(">> LW RvLAB: Warning! Transposition was needed. Done!")
  }  #else{comm3 <- comm2} #else {comm3 <- comm2[,-1]}
   else {
 

   #if (dim(agg)[1] != dim(comm3)[2]){
   	print(">> LW RvLAB: ERROR!!!")
	print(">> LW RvLAB: Dimensions do not match!")
	print(">> LW RvLAB: Check the values for matrix headers or the input dataset files used.")
	finalize()
	return(NA)
   }

	#convert it into a data frame
	comm3<-data.frame(comm3)

 	#print(">>> dime comm3:")
	#print(dim(comm3))
 

	#assign column names to the new data frame by taking the first column from the original data frame 
	#names(comm3) <- names(comm2[2:length(comm2)]) 
	

   
######## Inside Taxa2Dist
# These steps are the initialization operations of Taxa2Dist. Nothing changes here
#varstep <- FALSE
checkTaxa <- TRUE

rich <- apply(agg, 2, function(taxa) length(unique(taxa)))
S <- nrow(agg)
if (checkTaxa) {
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
  add <- rep(100/(ncol(agg) + checkTaxa), ncol(agg) + checkTaxa)
}
if (!is.null(names(add))) 
  names(add) <- c("Base", names(add)[-length(add)])
if (!checkTaxa) 
  add <- c(0, add)
  
# TP: Only matrices can be sent through pbdMPI so we transform 'add' to matrix
add <- matrix(add)




# TAXONDIVE code
comm3 <- as.matrix(comm3)
comm3Binary <- ifelse(comm3 > 0, 1, 0)

#del <- dstar <- dplus <- Ed <- Edstar <- edplus <- NULL





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


#### NEW ADDITION: Optimization

if (N<101) numOfMatricesOPTIMAL <-  2 else
if (N<10000) numOfMatricesOPTIMAL <-  10 else
if (N>50000) numOfMatricesOPTIMAL <-  25 else
if (processors < 5) numOfMatricesOPTIMAL <-  20 else numOfMatricesOPTIMAL <-  10


if (numOfMatricesOPTIMAL > numOfMatrices){
	m <- ceiling(N/numOfMatricesOPTIMAL)
	numOfMatrices <- numOfMatricesOPTIMAL
	
	comm.print("Number of submatrices after optimization:")
	comm.print( numOfMatrices)
}
#### END OF NEW ADDITION



# This is the number of rows in each submatrix that a processor is assigned to
step <- floor(m/processors)


########################################################################
########################################################################
comm3.all <- bcast(comm3, rank.source = 0)
comm3Binary.all <- bcast(comm3Binary, rank.source = 0)

delsumV2 <- numeric(length = nrow(comm3.all))
dstarsumV2 <- numeric(length = nrow(comm3.all))
dplussumV2 <- numeric(length = nrow(comm3Binary.all))
LambdaV2 <- numeric(length = nrow(comm3Binary.all))
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
# TP: The last node has less elements than the rest, i.e., the remaining elements
if (myrank == processors-1) {end<-k*m}


#comm.print("TEST TEST")

#comm.print(object.size(agg.all), all.rank = TRUE)
#comm.print(start, all.rank = TRUE)
#comm.print(end, all.rank = TRUE)

#comm.print( nrow(agg.all))

#comm.print("TEST", rank.print = 0)
#comm.print(dim(agg.all), all.rank = TRUE)
#comm.print(dim(add.all), all.rank = TRUE)
#comm.print(start, all.rank = TRUE)
#comm.print("End", rank.print = 0)
#comm.print(end, all.rank = TRUE)


#out <- matrix(add.all[1], nrow(agg.all), (end-start+1))
out <- matrix(add.all[1], (end-start+1), nrow(agg.all))



# TP: This is the main loop that is performed on each processor separately
for (i in 1:ncol(agg.all)) {
  out <- out + add.all[i + 1] * outer(agg.all[start:end, i], agg.all[, i], "!=")
}

#comm.print("TEST TEST")

########################################################################
########################################################################

  for(comm3Rows in 1:nrow(comm3.all) ) {
    x <- comm3.all[comm3Rows,]
	xBinary <- comm3Binary.all[comm3Rows,]	
	
	  # Calculate the portion of the outer that is equal to that of the outPartiallyComplete retrieved  
      outr.all <- outer(x[start:end], x)
      product <- outr.all*out
	  delsumV2temp1 <- sum(product[row(product)>(col(product)-(start-1))])
	  delsumV2temp2 <- reduce(delsumV2temp1, op = "sum")

	  dstarV2temp1 <- sum(outr.all[row(outr.all)>(col(outr.all)-(start-1))])
	  dstarV2temp2 <- reduce(dstarV2temp1, op = "sum")

      product <- product*out
	  
	  outrBinary.all <- outer(xBinary[start:end], xBinary)
      productBinary <- outrBinary.all*out
	  dplussumV2temp1 <- sum(productBinary[row(productBinary)>(col(productBinary)-(start-1))])
	  dplussumV2temp2 <- reduce(dplussumV2temp1, op = "sum")

	  productBinary <- productBinary*out
	  LambdaV2temp1 <- sum(productBinary[row(productBinary)>(col(productBinary)-(start-1))])
	  LambdaV2temp2 <- reduce(LambdaV2temp1, op = "sum")
	  
	  if (myrank == 0) {
		delsumV2[comm3Rows] <- delsumV2[comm3Rows] + delsumV2temp2
		dstarsumV2[comm3Rows] <- dstarsumV2[comm3Rows] +dstarV2temp2
		dplussumV2[comm3Rows] <- dplussumV2[comm3Rows] + dplussumV2temp2
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
  step <- floor(m/processors)
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
  
  
   for(comm3Rows in 1:nrow(comm3.all) ) {
    x <- comm3.all[comm3Rows,] 
	xBinary <- comm3Binary.all[comm3Rows,]	

	
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
	  
	  outrBinary.all <- outer(xBinary[start:end], xBinary)
      productBinary <- outrBinary.all*out
	  dplussumV2temp1 <- sum(productBinary[row(productBinary)>(col(productBinary)-(start-1))])
	  	  if (myrank == processors-1 && !CHECK2) { dplussumV2temp1 <-0 }
	  dplussumV2temp2 <- reduce(dplussumV2temp1, op = "sum")

	  productBinary <- productBinary*out
	  LambdaV2temp1 <- sum(productBinary[row(productBinary)>(col(productBinary)-(start-1))])
			if (myrank == processors-1 && !CHECK2) { LambdaV2temp1 <- 0 }
	  LambdaV2temp2 <- reduce(LambdaV2temp1, op = "sum")
	 
	  
	  if (myrank == 0) {
		delsumV2[comm3Rows] <- delsumV2[comm3Rows] + delsumV2temp2
		dstarsumV2[comm3Rows] <- dstarsumV2[comm3Rows] +dstarV2temp2
		dplussumV2[comm3Rows] <- dplussumV2[comm3Rows] + dplussumV2temp2
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
  rs <- rowSums(comm3)
  #del <- delsum/rs/(rs - 1) * 2 
  delV2 <- delsumV2/rs/(rs - 1) * 2 
  #dstar <- delsum/dstarsum
  dstarV2 <- delsumV2/dstarsumV2
  m <-rowSums(comm3Binary)
  dplus <- dplussumV2/m/(m-1)*2
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
	

	cnam <- names(comm2[2:length(comm2)])
	m <- as.numeric(m)
	names(m) <- cnam
	delV2 <- as.numeric(delV2)
	names(delV2) <- cnam
	dstarV2 <- as.numeric(dstarV2)
	names(dstarV2) <- cnam
	LambdaV2 <- as.numeric(LambdaV2)
	names(LambdaV2) <- cnam
	dplus <- as.numeric(dplus)
	names(dplus) <- cnam
	sd_Dplus <- as.numeric(sqrt(vardplusV2))
	names(sd_Dplus) <- cnam
	sdplus <- as.numeric(m*dplus)
	names(sdplus) <- cnam
 
	taxondiveOutput <- list(Species = m, 
		D = delV2, 
		Dstar = dstarV2, 
		Lambda = LambdaV2, 
		Dplus = dplus, 
		sd.Dplus= sd_Dplus, 
		SDplus = sdplus, 
		#ED = as.numeric(EdV2), 
		#EDstar = as.numeric(EdstarV2), 
		#EDplus = as.numeric(omebarV2))
		ED = EdV2, 
		EDstar = EdstarV2, 
		EDplus = omebarV2
		)

	class(taxondiveOutput) <- "taxondive"
	
	print(">> LW RvLAB: Summary")	
	print("Species")
	print(taxondiveOutput$Species)
	print("D")
	print(taxondiveOutput$D)  
	print("D*")
	print(taxondiveOutput$Dstar)
	print("D+")
	print(taxondiveOutput$Dplus)
	print("Lambda")
	print(taxondiveOutput$Lambda)
	print("sd.D+")
	print(taxondiveOutput$sd.Dplus)
	print("SD+")
	print(taxondiveOutput$SDplus)
	print("ED")
	print(taxondiveOutput$ED)
	print("ED*")
	print(taxondiveOutput$EDstar)
	print("ED+")
	print(taxondiveOutput$EDplus)
   

	
	
	#print(class(taxondiveOutput$Species))
	
	#summary(taxondiveOutput)

   #head(taxondiveOutput) 
   labels <- as.factor(cnam)
   
   
    ## Test with anastasis
     png("parallelTaxTaxOnPlot.png", height=600,width = 600)
	 plot(taxondiveOutput,pch=19,col=labels,cex = 1.7)
	 dev.off()
   }
   
   
}

# Stop the clock
 comm.print("Full program execution time for each processor", rank.source = 0)
 comm.print(proc.time() - fullProgramTimer, all.rank = T)





 
finalize()

