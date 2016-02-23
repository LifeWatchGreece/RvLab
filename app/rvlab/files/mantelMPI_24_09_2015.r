#  mpiexec -np 2 ./Rscript mantelMPI_22_09_2015.r "SPE_Lagoon16S_C10.csv" TRUE "SPE_Lagoon16S_C05.csv" TRUE "/home/varsos/R-3.1.1/Datasets/Christina_dataset/" "pearson" 999

rm(list = ls())

# Initial Parameters given by the user
args <- commandArgs(trailingOnly = TRUE)
dist1 <- args[1] # The name of the first (community) csv file. Not transposed
transposition1 <- as.logical(args[2]) # Choose to transpose the matrix (TRUE) or not (FALSE).
dist2 <- args[3] # The name of the second (community) csv file. Not transposed
transposition2 <- as.logical(args[4]) # Choose to transpose the matrix (TRUE) or not (FALSE).
datasetPath <- args[5] # The directory (path) where the datasets are stored
method <- args[6] # by default is "pearson"
permutations <- as.numeric(args[7])
#na.rm <- as.logical(args[6])
# outputPath <- args[7] # The directory (path) where the results 'll be stored


setwd(datasetPath)

xdis <- read.csv(dist1, header = TRUE, row.names = 1, sep = ",")
ydis <- read.csv(dist2, header = TRUE, row.names = 1, sep = ",")

if (transposition1) {
  xdis <- t(xdis)
} else if(transposition1 != TRUE) {
  xdis <- xdis
} else {
  stop("Wrong choice. For matrix's transposition choose 1 else 0.")
}

if (transposition2) {
  ydis <- t(ydis)
} else if (transposition2 != TRUE) {
  ydis <- ydis
} else {
  stop("Wrong choice. For matrix's transposition choose 1 else 0.")
}

if((permutations < 0) || is.numeric(permutations) != TRUE) {
  stop("Wrong permutations . Use non-negative values.")
}
# Transpose community matrix


library(vegan)
library(pbdMPI, quiet = TRUE)

init()

# Start the clock
fullProgramTimer = proc.time()

processors <- comm.size()
myrank <- comm.rank()

xdis <- vegdist(xdis)
ydis <- vegdist(ydis)

xdis <- as.dist(xdis)
ydis <- as.vector(as.dist(ydis))
na.rm <- FALSE

mainCalcTimer = proc.time()

if (na.rm) {
  use <- "complete.obs"
} else use <- "all.obs"
statistic <- cor(as.vector(xdis), ydis, method = method,
                 use = use)
variant <- match.arg(method, eval(formals(cor)$method))
variant <- switch(variant, pearson = "Pearson's product-moment correlation",
                  kendall = "Kendall's rank correlation tau", spearman = "Spearman's rank correlation rho",
                  variant)
N <- attr(xdis, "Size")

step1 <- floor(permutations/processors)
step2 <- permutations - step1*(processors - 1)

ptest <- function(take, ...) {
  permvec <- (xmat[take, take])[asdist]
  drop(cor(permvec, ydis, method = method, use = use))
}

# comm.print("--------------------------------------------------------------------")
if (myrank != (processors = 1)) {
  if (length(permutations) == 1) {
    if (permutations > 0) {
      arg <- NULL
      permat <- t(replicate(step1, permute::shuffle(N)))
    } 
  } else {
    permat <- as.matrix(permutations)
    if (ncol(permat) != N)
      stop(gettextf("'permutations' have %d columns, but data have %d observations",
                    ncol(permat), N))
    permutations <- nrow(permutations)
  }
  
  if(permutations) {
    start <- myrank*step1 + 1
    end <- (myrank+1)*step1
    
    perm <- numeric(step1)
    xmat <- as.matrix(xdis)
    asdist <- row(xmat) > col(xmat)
    perm <- sapply(1:step1, function(i, ...) ptest(permat[i, ], ...))
    sgnf <- (sum(perm >= statistic))	
  } else {
    signif <- NA
    perm <- NULL
  }
} else {
  arg <- NULL
  permat <- t(replicate(step2, permute::shuffle(N)))
  permat2 <- permat
  
  if(permutations) {
    start <- (myrank + 1)*step1 + 1
    end <- (myrank + 1)*step1 + step2
    
    perm <- numeric(step2)
    xmat <- as.matrix(xdis)
    asdist <- row(xmat) > col(xmat)
    
    perm <- sapply(1:step2, function(i, ...) ptest(permat2[i, ], ...))
    sgnf <- (sum(perm >= statistic))
  } else {
    signif <- NA
    perm <- NULL
  }  
}


all.perm <- gather(perm)
all.sgnf <- reduce(sgnf, op = 'sum')

if(myrank == 0) {
  
  dmc <- NULL
  for(i in 1:length(all.perm)) {
    dmc <- c(dmc, all.perm[[i]])
  }
  all.perm <- dmc; rm(dmc)
  signif <- (all.sgnf + 1)/(permutations + 1)
}

comm.print("method", rank.print = 0)
comm.print(method, rank.print = 0)
comm.print("permutations", rank.print = 0)
comm.print(permutations, rank.print = 0)

res <- list(call = match.call(definition = function(xdis, ydis, method, permutations)NULL,
            quote(mantel(xdis, ydis, method, permutations))), method = variant,
            statistic = statistic, signif = signif, perm = all.perm, permutations = permutations)


class(res) <- "mantel"

## comm.print(res[[1]])
## comm.print(res[[2]])
## comm.print(res[[3]])
## comm.print(res[[4]])
## comm.print(length(res[[5]]))
## comm.print(res[[6]])

comm.print(res, rank.print = 0)

# Stop the clock
comm.print("Full program execution time for each processor", rank.source = 0)
comm.print(proc.time() - fullProgramTimer, all.rank = T)

comm.print("Main calculations time", rank.source = 0)
comm.print(proc.time() - mainCalcTimer, all.rank = T)

comm.print("Mantel class object size (Mb)", rank.source = 0)
comm.print(object.size(res)/(1024^2), all.rank = T)

comm.print("Object size of the main calculation", rank.source = 0)
comm.print(object.size(res[[5]])/(1024^2), rank.source = T)


#
finalize()
