rm(list = ls())

# Inputs
args <- commandArgs(trailingOnly = TRUE)
dataMatrix <- args[1] # The name of the csv file. Not transposed
transposition <- as.logical(args[2]) # Transpose (YES) or not (NO) the matrix.
groupingFactor <- args[3] # The name of the factor file for grouping observations.
SelectFormula <- as.numeric(args[4]) # Choose 1 for Single, 2 for Multiple parameter.
SelectFactor1 <- as.numeric(args[5]) # Choose 1st Factor.
SelectFactor2 <- as.numeric(args[6]) # Choose 2nd Factor.
datasetPath <- args[7] # The directory (path) where the datasets are stored
permutations <- as.numeric(args[8])
method <- args[9]
# strata <- args[10]

setwd(datasetPath)

SPE.dist <- read.csv(dataMatrix, header = TRUE, row.names = 1, sep = ",")
Factors <- read.csv(groupingFactor, header = TRUE, row.names = 1, sep = ",")

# Transpose community matrix
if (transposition) {
  mat <- t(SPE.dist)#; mt <- "t(SPE.dist) ~ Factors[, 1]"
} else if (transposition != TRUE) {
  mat <- SPE.dist# ; mt <- "SPE.dist ~ Fa"
} else {
  stop("Wrong choice. For matrix's transposition choose 1 else 0.")
}

Ft <- ncol(Factors)

# Check if the arguments are greater than Factor's columns.
if (((SelectFactor1 <= 0) || (SelectFactor1 > Ft)) || ((SelectFactor2 <= 0) || (SelectFactor2 > Ft))) {
  stop("Wrong choice of Factors, transcends available Factors choices.")
}

# Choose Single or Multiple parameter
if (SelectFormula == 1) {
  print("Single parameter")
  formula = mat ~ Factors[, SelectFactor1]
  dt <- "mat ~ Factors[, SelectFactor1]"
} else if (SelectFormula == 2) {
  print("Multiple parameter")
  formula = mat ~ Factors[, SelectFactor1]*Factors[, SelectFactor2]
  dt <- "mat ~ Factors[, SelectFactor1]*Factors[, SelectFactor2]"
} else {
  stop("Wrong formula choice. Choose 1 for Single or 2 for Multiple parameter.")
}

if((permutations < 0) || is.numeric(permutations) != TRUE) {
  stop("Negative permutations number. Use non-negative values.")
}

data <- Factors
contr.unordered = "contr.sum"
contr.ordered = "contr.poly"
strata <- NULL
# load libraries
library(vegan)
# library(RPostgreSQL)
library(pbdMPI, quiet = TRUE)

init()

# Start the clock
fullProgramTimer = proc.time()

# TP: Get the size of the cluster
processors <- comm.size()
myrank <- comm.rank()

TOL <- 1e-07
Terms <- terms(formula, data = data)
lhs <- formula[[2]]
lhs <- eval(lhs, data, parent.frame())
formula[[2]] <- NULL
rhs.frame <- model.frame(formula, data, drop.unused.levels = TRUE)
op.c <- options()$contrasts
options(contrasts = c(contr.unordered, contr.ordered))
rhs <- model.matrix(formula, rhs.frame)
options(contrasts = op.c)
grps <- attr(rhs, "assign")
qrhs <- qr(rhs)
rhs <- rhs[, qrhs$pivot, drop = FALSE]
rhs <- rhs[, 1:qrhs$rank, drop = FALSE]
grps <- grps[qrhs$pivot][1:qrhs$rank]
u.grps <- unique(grps)
nterms <- length(u.grps) - 1

H.s <- lapply(2:length(u.grps), function(j) {
  Xj <- rhs[, grps %in% u.grps[1:j]]
  qrX <- qr(Xj, tol = TOL)
  Q <- qr.Q(qrX)
  tcrossprod(Q[, 1:qrX$rank])
})

if (inherits(lhs, "dist")) {
  if (any(lhs < -TOL)) 
    stop("dissimilarities must be non-negative")
  dmat <- as.matrix(lhs^2)
} else {
  dist.lhs <- as.matrix(vegdist(lhs, method = method))
  dmat <- dist.lhs^2
}

n <- nrow(dmat)
G <- -sweep(dmat, 1, rowMeans(dmat))/2
SS.Exp.comb <- sapply(H.s, function(hat) sum(G * t(hat)))
SS.Exp.each <- c(SS.Exp.comb - c(0, SS.Exp.comb[-nterms]))
H.snterm <- H.s[[nterms]]
tIH.snterm <- t(diag(n) - H.snterm)

if (length(H.s) > 1) 
  for (i in length(H.s):2) H.s[[i]] <- H.s[[i]] - H.s[[i - 
                                                         1]]
SS.Res <- sum(G * tIH.snterm)
df.Exp <- sapply(u.grps[-1], function(i) sum(grps == i))
df.Res <- n - qrhs$rank

if (inherits(lhs, "dist")) {
  beta.sites <- qr.coef(qrhs, as.matrix(lhs))
  beta.spp <- NULL
} else {
  beta.sites <- qr.coef(qrhs, dist.lhs)
  beta.spp <- qr.coef(qrhs, as.matrix(lhs))
}

colnames(beta.spp) <- colnames(lhs)
colnames(beta.sites) <- rownames(lhs)
F.Mod <- (SS.Exp.each/df.Exp)/(SS.Res/df.Res)

f.test <- function(tH, G, df.Exp, df.Res, tIH.snterm) {
  (sum(G * tH)/df.Exp)/(sum(G * tIH.snterm)/df.Res)
}

SS.perms <- function(H, G, I) {
  c(SS.Exp.p = sum(G * t(H)), S.Res.p = sum(G * t(I - H)))
}

if (missing(strata)) 
  strata <- NULL

permuted.index <- function (n, strata) {
  if (missing(strata) || is.null(strata)) 
    out <- sample.int(n, n)
  else {
    out <- 1:n
    inds <- names(table(strata))
    for (is in inds) {
      gr <- out[strata == is]
      if (length(gr) > 1) 
        out[gr] <- sample(gr, length(gr))
    }
  }
  out
}

step1 <- floor(permutations/processors)
step2 <- permutations - step1*(processors - 1)

if (myrank != (processors - 1)) {
  
  p <- sapply(1:step1, function(x) permuted.index(n, strata = strata))
  
  tH.s <- lapply(H.s, t)
  
  f.perms <- sapply(1:nterms, function(i) {
    sapply(1:step1, function(j) {
      f.test(tH.s[[i]], G[p[, j], p[, j]], df.Exp[i], df.Res, 
             tIH.snterm)
    })
  })
  
} else {
  p <- sapply(1:step2, function(x) permuted.index(n, strata = strata))
  
  tH.s <- lapply(H.s, t)

  f.perms <- sapply(1:nterms, function(i) {
    sapply(1:step2, function(j) {
        f.test(tH.s[[i]], G[p[, j], p[, j]], df.Exp[i], df.Res, 
	           tIH.snterm)
    })
  })
}

all.f <- gather(f.perms)

if(myrank == 0) {
  
  dmc <- NULL
  for(i in 1:length(all.f)) {
    dmc <- rbind(dmc, all.f[[i]])
  }
  f.perms <- dmc; rm(dmc)
}


f.perms <- round(f.perms, 12)
F.Mod <- round(F.Mod, 12)

SumsOfSqs = c(SS.Exp.each, SS.Res, sum(SS.Exp.each) + SS.Res)

tab <- data.frame(Df = c(df.Exp, df.Res, n - 1), SumsOfSqs = SumsOfSqs, 
                  MeanSqs = c(SS.Exp.each/df.Exp, SS.Res/df.Res, NA), F.Model = c(F.Mod, 
                                                                                  NA, NA), R2 = SumsOfSqs/SumsOfSqs[length(SumsOfSqs)], 
                  P = c((rowSums(t(f.perms) >= F.Mod) + 1)/(permutations + 
                                                              1), NA, NA))

rownames(tab) <- c(attr(attr(rhs.frame, "terms"), "term.labels")[u.grps], 
                   "Residuals", "Total")
colnames(tab)[ncol(tab)] <- "Pr(>F)"
attr(tab, "heading") <- "Terms added sequentially (first to last)\n"

class(tab) <- c("anova", class(tab))

# comm.print(print("Formula =") formula, rank.source = 0)

comm.print("formula", rank.print = 0)
comm.print(dt, rank.print = 0)
# comm.print("data", rank.print = 0)
# comm.print("Factors", rank.print = 0) 
comm.print("permutations", rask.source = 0)
comm.print(permutations, rank.print = 0)
comm.print("distance", rank.source = 0)
comm.print(method, rank.source = 0)
# comm.print("strata", rank.source = 0)
# comm.print(strata, rank.source = 0)

# out <- list(aov.tab = tab, call = match.call(definition = function(formula, data, permutations, distnace, strata)NULL, 
#                                              quote(adonis(formula, Factors, permutations, method, strata))), 
#             coefficients = beta.spp, coef.sites = beta.sites, f.perms = f.perms, model.matrix = rhs, 
#             terms = Terms)

out <- list(aov.tab = tab, call = match.call(definition = function(formula, data, permutations, distance)NULL,
                                             quote(adonis(formula, Factors, permutations, method))),
            coefficients = beta.spp, coef.siztes = beta.sites, f.perms = f.perms, model.matrix = rhs,
	    terms = Terms)

class(out) <- "adonis"
comm.print(out, rank.print = 0)

# Stop the clock
comm.print("Full program execution time for each processor", rank.source = 0)
comm.print(proc.time() - fullProgramTimer, all.rank = T)

#
finalize()
