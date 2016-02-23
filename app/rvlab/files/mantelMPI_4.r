#                            mantel
#--------------------------------------------------------------------------
print("summary")

rm(list = ls())

library(vegan)
library(pbdMPI, quiet = TRUE)

# Initial Parameters given by the user
args <- commandArgs(trailingOnly = TRUE)
dist1 <- args[1] # The name of the first (community) csv file. Not transposed
dist2 <- args[2] # The name of the second (community) csv file. Not transposed
datasetPath <- args[3] # The directory (path) where the datasets are stored
method <- args[4] # by default is "pearson"
permutations <- as.numeric(args[5])
na.rm <- args[6]

#setwd("/home/varsos/R-3.1.1/Datasets/Christina_dataset")
setwd(datasetPath)

#xdis <- dist1;#vegdist(t(read.csv(dist1, header = TRUE, row.names = 1 ,sep = ",")))
#ydis <- dist2;#vegdist(t(read.csv(dist2, header = TRUE, row.names = 1 ,sep = ",")))

xdis <- get(load(dist1));
ydis <- get(load(dist2));

init()

processors <- comm.size()
myrank <- comm.rank()

#xdis <- as.dist(xdis)
#ydis <- as.vector(as.dist(ydis))
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
#!#!#!#!#!#!#!#!#!#!#!#!#
if (length(permutations) == 1) {
  if (permutations > 0) {
#    arg <- if (missing(strata)) 
#      NULL
#    else strata
    arg <- NULL
    if(myrank != (processors - 1)){
      permat <- t(replicate((permutations/processors), permute::shuffle(N)))
    } else permat <- t(replicate(permutations - ((processors - 1)*ceiling(permutations/processors))
                                 , permute::shuffle(N)))
  }
} else {
  if(myrank != (processors - 1)){
    permat <- as.matrix((permutations/processors))
    if (ncol(permat) != N) 
      stop(gettextf("'permutations' have %d columns, but data have %d observations", 
                    ncol(permat), N))
    permutations <- nrow((permutations/processors))
  } else {
    Asd <- permutations - ((processors - 1)*ceiling(permutations/processors))
    permat <- as.matrix(Asd)
    if (ncol(permat) != N) 
      stop(gettextf("'permutations' have %d columns, but data have %d observations", 
                    ncol(permat), N))
    permutations <- nrow(Asd)
  }
}

if (permutations) {
  if(myrank != (processors - 1)) {
    perm <- numeric((permutations/processors))
    xmat <- as.matrix(xdis)
    asdist <- row(xmat) > col(xmat)
    ptest <- function(take, ...) {
      permvec <- (xmat[take, take])[asdist]
      drop(cor(permvec, ydis, method = method, use = use))
    }
    perm <- sapply(1:(permutations/processors), function(i, ...) ptest(permat[i, 
                                                                              ], ...))
    signif <- (sum(perm >= statistic) + 1)/((permutations/processors) + 
                                              1)
  } else {
    Asd <- permutations - ((processors - 1)*ceiling(permutations/processors))
    perm <- numeric(Asd)
    xmat <- as.matrix(xdis)
    asdist <- row(xmat) > col(xmat)
    ptest <- function(take, ...) {
      permvec <- (xmat[take, take])[asdist]
      drop(cor(permvec, ydis, method = method, use = use))
    }
    perm <- sapply(1:Asd, function(i, ...) ptest(permat[i, 
                                                        ], ...))
    signif <- (sum(perm >= statistic) + 1)/(Asd + 1)
  }
} else {
  signif <- NA
  perm <- NULL
}
# finalize()
#!#!#!#!#!#!#!#!#!#!#!#
# res <- list(call = as.call(list(quote(mantel), quote(dist1), quote(dist2), method = method,
#                                permutations = permutations)), method = variant, statistic = statistic, 
#            signif = signif, perm = perm, permutations = permutations)

res <- list(call = match.call(definition = function(xdis, ydis, method, permutations)NULL, 
          quote(mantel(xdis, ydis, method, permutations))), method = variant, 
		  statistic = statistic, signif = signif, perm = perm, permutations = permutations)
# if (!missing(strata)) {
#  res$strata <- deparse(substitute(strata))
#  res$stratum.values <- strata
# }

class(res) <- "mantel"
comm.print(res)

# Stop the clock
# comm.print("Full program execution time for each processor",rank.source = 0)
# comm.print(proc.time() - fullProgramTimer, all.rank=T)
finalize()
#}
