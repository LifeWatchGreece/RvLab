# mpiexec -np 4 ./Rscript anosimMPI_22_09_2015.r "SPE_Lagoon16S_C10.csv" TRUE "Factors_Lagoon16S.csv" 1 "/home/varsos/R-3.1.1/Datasets/Christina_dataset/" 9999 "bray"

rm(list=ls())

# Initial Parameters given by the user
args <- commandArgs(trailingOnly = TRUE)
dataMatrix <- args[1] # The name of the csv file.
transposition <- as.logical(args[2]) # Choose to transpose the matrix (TRUE) or not (FALSE).
groupingFactor <- args[3] # The name of the factor file for grouping observations.
SelectFactor <- as.numeric(args[4]) # Choose the desired factors's column.
datasetPath <- args[5] # The directory (path) where the datasets are stored.
permutations <- as.numeric(args[6])
distance <- args[7]

# Start the clock
fullProgramTimer <- proc.time()

setwd(datasetPath)

SPE.dist <- read.csv(dataMatrix, header = TRUE, row.names = 1, sep = ",")
Factors <- read.csv(groupingFactor, header = TRUE, row.names = 1, sep = ",")

if(transposition) {
  dat <- t(SPE.dist)
} else if (transposition != TRUE) {
  dat <- SPE.dist
} else {
  stop("Wrong choice. Choose to transpose the matrix (TRUE) or not (FALSE).")
}

if((SelectFactor <= 0) || (SelectFactor > ncol(Factors))) {
  stop("Wrong choice of Factors, transcends available Factors choices.")
}

if((permutations < 0) || is.numeric(permutations) != TRUE) {
  stop("Negative permutations number. Use non-negative values.")
}

grouping <- Factors[, SelectFactor]

# load libraries
library(vegan)
library(pbdMPI, quiet = TRUE)

init()

# TP: Get the size of the cluster
processors <- comm.size()
myrank <- comm.rank()


if (inherits(dat, "dist")) { 
  x <- dat} else if (is.matrix(dat) && nrow(dat) == ncol(dat) && all(dat[lower.tri(dat)] ==  t(dat)[lower.tri(dat)])) {
    x <- dat
    attr(x, "method") <- "user supplied square matrix"
  } else {
    x <- vegdist(dat, method = distance)  
  }

if (any(x < -sqrt(.Machine$double.eps))) 
  warning("some dissimilarities are negative -- is this intentional?")

sol <- c(call = match.call(definition = function(dat, grouping, permutations, distance)NULL, quote(anosim(dat, grouping, permutations, distance))))
grouping <- as.factor(grouping)
matched <- function(irow, icol, grouping) {
  grouping[irow] == grouping[icol]
}
x.rank <- rank(x)
N <- attr(x, "Size")
div <- length(x)/2
irow <- as.vector(as.dist(row(matrix(nrow = N, ncol = N))))
icol <- as.vector(as.dist(col(matrix(nrow = N, ncol = N))))
within <- matched(irow, icol, grouping)
aver <- tapply(x.rank, within, mean)

statistic <- -diff(aver)/div
cl.vec <- rep("Between", length(x))
take <- as.numeric(irow[within])
cl.vec[within] <- levels(grouping)[grouping[take]]
cl.vec <- factor(cl.vec, levels = c("Between", levels(grouping)))

if (permutations) {
  
  if 	(myrank != (processors-1)) {
    perm <- rep(0, ceiling(permutations/processors))
  } else perm <- rep(0, permutations - ( (processors-1) * ceiling(permutations/processors)))
  
  
  for (i in 1:(permutations/processors)) {
    take <- permute::shuffle(N)
    cl.perm <- grouping[take]
    tmp.within <- matched(irow, icol, cl.perm)
    tmp.ave <- tapply(x.rank, tmp.within, mean)
    perm[i] <- -diff(tmp.ave)/div
  }
  
  if (myrank == 0) {print("Number of permutations per processor:")}
  
  print(length(perm))
  n.permsum <- sum(perm >= statistic)
  
  all.permsum <- reduce(n.permsum, op='sum')
  
  if (myrank == 0) {
    p.val <- (1 + all.permsum)/(1 + permutations)
    
    print("p.val = ")
    print(p.val)
    
    sol <- as.call(list(quote(anosim), quote(mat), quote(Factors[, SelectFactor]), 
                        permutations = permutations, method = distance))
    
    sol$call <- as.call(list(quote(anosim), quote(mat), quote(Factors[, SelectFactor]), 
                             permutations = permutations, method = distance))		
    sol$signif <- p.val
    sol$perm <- perm
    sol$permutations <- permutations
    sol$statistic <- as.numeric(statistic)
    sol$class.vec <- cl.vec
    sol$dis.rank <- x.rank
    sol$dissimilarity <- attr(x, "method")
  }
  
}
class(sol) <- "anosim"

# comm.print("permutations", rank.print = 0)
# comm.print(permutations, rank.print = 0)
# comm.print("method", rank.print = 0)
# comm.print(distance, rank.print = 0)

comm.print(sol)

# Stop the clock
comm.print("Full program execution time for each processor",rank.source = 0)
comm.print(proc.time() - fullProgramTimer, all.rank=T)


# Results
# comm.print("Significance:", rank.source = 0)
# comm.print(sol[[2]], rank.source = T)

# comm.print("Statistic R", rank.source = 0)
# comm.print(sol[[5]], rank.source = T)

# comm.print("Factors", rank.source = 0)
# comm.print(length(sol[[6]]), rank.source = T)

# comm.print("Community", rank.source = 0)
# comm.print(length(sol[[7]]), rank.source = T)

# comm.print("Final result's size", rank.source = 0)
# comm.print((object.size(sol)/(1024^2)), all.rank = T)
finalize()
