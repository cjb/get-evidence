<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

require_once ("lib/article.php");
require_once ("lib/hapmap.php");

function evidence_create_tables ()
{
  theDb()->query ("
  CREATE TABLE IF NOT EXISTS variants (
  variant_id SERIAL,
  variant_gene VARCHAR(16),
  variant_aa_pos INT UNSIGNED,
  variant_aa_from ENUM('Ala','Arg','Asn','Asp','Cys','Gln','Glu','Gly','His','Ile','Leu','Lys','Met','Phe','Pro','Ser','Thr','Trp','Tyr','Val','Stop'),
  variant_aa_to ENUM('Ala','Arg','Asn','Asp','Cys','Gln','Glu','Gly','His','Ile','Leu','Lys','Met','Phe','Pro','Ser','Thr','Trp','Tyr','Val','Stop'),
  UNIQUE (variant_gene, variant_aa_pos, variant_aa_from, variant_aa_to)
)");
  theDb()->query ("ALTER TABLE variants ADD variant_rsid BIGINT UNSIGNED, ADD UNIQUE (variant_rsid)");
  theDb()->query ("
  CREATE TABLE IF NOT EXISTS edits (
  edit_id SERIAL,
  variant_id BIGINT NOT NULL REFERENCES variants.variant_id,
  previous_edit_id BIGINT,
  is_draft TINYINT NOT NULL DEFAULT 1,
  is_delete TINYINT NOT NULL DEFAULT 0,
  edit_oid VARCHAR(255),
  edit_timestamp DATETIME,
  signoff_oid VARCHAR(255),
  signoff_timestamp DATETIME,
  variant_impact ENUM('pathogenic','likely pathogenic','unknown','likely benign','benign','likely protective','protective','other','pharmacogenetic','likely pharmacogenetic','none','not reviewed') NOT NULL DEFAULT 'not reviewed',
  variant_dominance ENUM('unknown','dominant','recessive','other','undefined') NOT NULL DEFAULT 'unknown',
  variant_quality CHAR(5),
  variant_quality_text TEXT,
  summary_short TEXT,
  summary_long TEXT,
  talk_text TEXT,
  article_pmid INT UNSIGNED NOT NULL,
  genome_id BIGINT UNSIGNED NOT NULL,
  disease_id BIGINT UNSIGNED NOT NULL,
  
  INDEX (variant_id,edit_timestamp),
  INDEX (edit_oid, edit_timestamp),
  INDEX (previous_edit_id, edit_oid),
  INDEX (variant_id, article_pmid, genome_id, edit_timestamp),
  INDEX (is_draft, edit_timestamp)
)");
  theDb()->query ("ALTER TABLE edits ADD disease_id BIGINT UNSIGNED NOT NULL AFTER genome_id");

  foreach (array ("snap_latest", "snap_release") as $t) {
      theDb()->query ("CREATE TABLE IF NOT EXISTS `$t` LIKE edits");
      theDb()->query ("ALTER TABLE `$t` ADD disease_id BIGINT UNSIGNED NOT NULL");
      theDb()->query ("ALTER TABLE `$t` ADD UNIQUE `snapkey` (variant_id, article_pmid, genome_id, disease_id)");
      theDb()->query ("ALTER TABLE `$t` DROP INDEX `snap_key`");
  }

  theDb()->query ("CREATE TABLE IF NOT EXISTS genomes (
  genome_id SERIAL,
  global_human_id VARCHAR(16) NOT NULL,
  name VARCHAR(128),
  UNIQUE(global_human_id))");

  theDb()->query ("CREATE TABLE IF NOT EXISTS datasets (
  dataset_id VARCHAR(16) NOT NULL,
  genome_id BIGINT UNSIGNED NOT NULL,
  dataset_url VARCHAR(255),
  sex ENUM('M','F'),
  INDEX(genome_id,dataset_id),
  UNIQUE(dataset_id))");

  theDb()->query ("CREATE TABLE IF NOT EXISTS diseases (
  disease_id SERIAL,
  disease_name VARCHAR(255) NOT NULL,
  UNIQUE disease_name_unique (disease_name))");

  theDb()->query ("CREATE TABLE IF NOT EXISTS variant_occurs (
  variant_id BIGINT UNSIGNED NOT NULL,
  rsid BIGINT UNSIGNED NOT NULL,
  dataset_id VARCHAR(16) NOT NULL,
  UNIQUE(variant_id,dataset_id,rsid),
  INDEX `rsid` (`rsid`)
  )");
  theDb()->query ("ALTER TABLE variant_occurs
  ADD zygosity ENUM('heterozygous','homozygous')
  ");
  theDb()->query ("ALTER TABLE variant_occurs
  ADD chr CHAR(6),
  ADD chr_pos INT UNSIGNED,
  ADD allele CHAR(1),
  ADD INDEX chr_pos_allele (chr,chr_pos,allele)
  ");

  theDb()->query ("CREATE TABLE IF NOT EXISTS variant_locations (
  chr CHAR(6) NOT NULL,
  chr_pos INT UNSIGNED NOT NULL,
  allele CHAR(1) NOT NULL,
  rsid BIGINT UNSIGNED,
  gene_aa VARCHAR(32),
  INDEX chr_pos_allele (chr, chr_pos, allele),
  INDEX (rsid))");
  theDb()->query ("ALTER TABLE variant_locations
  ADD variant_id BIGINT UNSIGNED,
  ADD INDEX(variant_id),
  ADD UNIQUE(chr,chr_pos,allele,gene_aa)");

  theDb()->query ("CREATE TABLE IF NOT EXISTS taf (
  chr CHAR(6) NOT NULL,
  chr_pos INT UNSIGNED NOT NULL,
  allele CHAR(1) NOT NULL,
  taf TEXT,
  UNIQUE(chr, chr_pos, allele)
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS variant_external (
  variant_id BIGINT UNSIGNED NOT NULL,
  tag CHAR(16),
  content TEXT,
  url VARCHAR(255),
  updated DATETIME,
  INDEX(variant_id,tag),
  INDEX(tag,variant_id)
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS genetests_genes (
  gene CHAR(16) NOT NULL PRIMARY KEY
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS genetests (
  gene CHAR(16) NOT NULL PRIMARY KEY,
  testable TINYINT NOT NULL,
  reviewed TINYINT NOT NULL
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS allele_frequency (
  chr CHAR(6),
  chr_pos INT UNSIGNED,
  allele CHAR(1),
  dbtag CHAR(6),
  num INT UNSIGNED,
  denom INT UNSIGNED,
  UNIQUE(chr,chr_pos,allele,dbtag)
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS variant_frequency (
  variant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  num INT UNSIGNED,
  denom INT UNSIGNED,
  f FLOAT,
  INDEX(f))");

  theDb()->query ("CREATE TABLE IF NOT EXISTS dbsnp (
  id INT UNSIGNED NOT NULL PRIMARY KEY,
  chr CHAR(7) NOT NULL,
  chr_pos INT UNSIGNED NOT NULL,
  orient TINYINT UNSIGNED NOT NULL,
  INDEX chr_pos_orient (chr,chr_pos,orient)
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS gene_disease (
  gene VARCHAR(32) NOT NULL,
  disease_id BIGINT UNSIGNED NOT NULL,
  dbtag VARCHAR(12) NOT NULL,
  UNIQUE `gene_disease_dbtag` (gene,disease_id,dbtag),
  INDEX `disease_index` (disease_id,gene,dbtag),
  INDEX `dbtag_index` (dbtag)
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS gene_canonical_name (
  aka VARCHAR(32) NOT NULL,
  official VARCHAR(32) NOT NULL,
  UNIQUE aka_key (aka))");

  theDb()->query ("CREATE TABLE IF NOT EXISTS variant_disease (
  variant_id BIGINT UNSIGNED NOT NULL,
  disease_id BIGINT UNSIGNED NOT NULL,
  dbtag CHAR(6) NOT NULL,
  UNIQUE `variant_disease_dbtag` (variant_id,disease_id,dbtag),
  INDEX `disease_index` (disease_id,variant_id,dbtag),
  INDEX `dbtag_index` (dbtag)
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS flat_summary (
  variant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  updated TIMESTAMP,
  flat_summary TEXT
  )");
  theDb()->query ("ALTER TABLE flat_summary ADD autoscore TINYINT after variant_id");
  theDb()->query ("ALTER TABLE flat_summary ADD webscore CHAR(1) after autoscore");
  theDb()->query ("ALTER TABLE flat_summary ADD n_genomes INT after webscore");
  theDb()->query ("ALTER TABLE flat_summary ADD INDEX webscore_index (webscore)");
  theDb()->query ("ALTER TABLE flat_summary ADD INDEX webscore_priority_index (genome_hits, autoscore)");

  theDb()->query ("CREATE TABLE IF NOT EXISTS web_vote_history (
  vote_id SERIAL,
  variant_id BIGINT UNSIGNED NOT NULL,
  url VARCHAR(255) NOT NULL,
  vote_oid VARCHAR(255) NOT NULL,
  vote_timestamp TIMESTAMP,
  vote_score TINYINT,
  INDEX (variant_id,vote_oid,vote_timestamp),
  INDEX (vote_oid,variant_id),
  INDEX (vote_oid,vote_timestamp)
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS web_vote_latest (
  variant_id BIGINT UNSIGNED NOT NULL,
  url VARCHAR(255) NOT NULL,
  vote_oid VARCHAR(255) NOT NULL,
  vote_timestamp TIMESTAMP,
  vote_score TINYINT,
  UNIQUE (variant_id,url(125),vote_oid(125))
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS web_vote (
  variant_id BIGINT UNSIGNED NOT NULL,
  url VARCHAR(255) NOT NULL,
  vote_1 INT DEFAULT 0,
  vote_0 INT DEFAULT 0,
  UNIQUE (variant_id,url)
  )");

  theDb()->query ("
CREATE TABLE IF NOT EXISTS yahoo_boss_cache (
 variant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 xml TEXT
)");
  theDb()->query ("ALTER TABLE yahoo_boss_cache ADD hitcount INT UNSIGNED");
  theDb()->query ("ALTER TABLE yahoo_boss_cache ADD retrieved DATETIME");
}

function evidence_get_genome_id ($global_human_id)
{
  $genome_id = theDb()->getOne ("SELECT genome_id FROM genomes WHERE global_human_id=?",
				array ($global_human_id));
  if ($genome_id > 0)
    return $genome_id;
  $q = theDb()->query ("INSERT INTO genomes SET global_human_id=?",
		       array ($global_human_id));
  if (theDb()->isError($q)) {
    $genome_id = theDb()->getOne ("SELECT genome_id FROM genomes WHERE global_human_id=?",
				  array ($global_human_id));
    if ($genome_id > 0)
      return $genome_id;
    die ("evidence_get_genome_id: DB error: " . $q->getMessage() . " -- lookup failed");
  }
  else
    return theDb()->getOne ("SELECT LAST_INSERT_ID()");
}

function evidence_get_variant_id ($gene,
				  $aa_pos=false,
				  $aa_from=false,
				  $aa_to=false,
				  $create_flag=false)
{
  if ($aa_pos === false && ereg ('^rs([0-9]+)$', $gene, $regs)) {

    // Return the gene/AA variant if one is already known to be caused
    // by this rsid
    $variant_id = theDb()->getOne ("SELECT v.variant_id
				 FROM variant_occurs vo
				 LEFT JOIN variants v
				  ON v.variant_id = vo.variant_id
				 WHERE vo.rsid=?
				  AND v.variant_rsid IS NULL
				  AND v.variant_id IS NOT NULL
				 LIMIT 1",
				   array ($regs[1]));
    if (theDb()->isError ($variant_id)) die ($variant_id->getMessage());
    if ($variant_id)
      return $variant_id;

    if ($create_flag) {
      $q = theDb()->query ("INSERT IGNORE INTO variants
			    SET variant_rsid=?",
			   array ($regs[1]));
      if (!theDb()->isError($q) &&
	  theDb()->affectedRows())
	return theDb()->getOne ("SELECT LAST_INSERT_ID()");
    }
    return theDb()->getOne ("SELECT variant_id FROM variants
				WHERE variant_rsid=?",
			    array ($regs[1]));
  }

  if ($aa_pos === false) {
    if (ereg ("^([-A-Za-z0-9_]+)[- ]+([A-Za-z]+)([0-9]+)([A-Za-z\\*]+)$", $gene, $regs)) {
      $gene = $regs[1];
      $aa_from = $regs[2];
      $aa_pos = $regs[3];
      $aa_to = $regs[4];
    }
    else
      return null;
  }
  else if (!aa_sane("$aa_from$aa_pos$aa_to"))
    return null;

  $gene = strtoupper ($gene);

  $aa_from = aa_long_form ($aa_from);
  $aa_to = aa_long_form ($aa_to);

  if ($create_flag) {
    $official_gene = theDb()->getOne ("SELECT official FROM gene_canonical_name WHERE aka=?", array ($gene));
    if (!theDb()->isError ($official_gene) && strlen($official_gene) && !theDb()->getOne("SELECT 1 FROM variants WHERE variant_gene=? AND variant_aa_pos=? AND variant_aa_from=? AND variant_iaa_to=?", array ($gene, $aa_pos, $aa_from, $aa_to)))
      $gene = $official_gene;

    $q = theDb()->query ("INSERT IGNORE INTO variants
			SET variant_gene=?,
			variant_aa_pos=?,
			variant_aa_from=?,
			variant_aa_to=?",
		    array ($gene, $aa_pos, $aa_from, $aa_to));
    if (!theDb()->isError($q) &&
	theDb()->affectedRows())
      return theDb()->getOne ("SELECT LAST_INSERT_ID()");
  }
  return theDb()->getOne ("SELECT variant_id FROM variants
				WHERE variant_gene=?
				AND variant_aa_pos=?
				AND variant_aa_from=?
				AND variant_aa_to=?",
			  array ($gene, $aa_pos, $aa_from, $aa_to));
}

function evidence_approve ($edit_id, $signoff_oid)
{
  theDb()->query ("UPDATE edits SET signoff_oid=?, signoff_timestamp=now() WHERE edit_id=? AND signoff_oid IS NULL", array ($signoff_oid, $edit_id));
  if (theDB()->affectedRows() != 1)
    {
      if (theDb()->getOne ("SELECT 1 FROM edits WHERE edit_id=?", $edit_id))
	{
	  // TODO: handle warning ("already approved")
	}
      else
	{
	  // TODO: handle warning ("no such entry")
	}
    }
}

function evidence_get_edit ($edit_id)
{
  return theDb()->getRow ("SELECT * FROM edits WHERE edit_id=?",
			  array ($edit_id));
}

function evidence_generate_edit ($previous_edit_id=null,
				 $variant_id=null,
				 $row=array())
{
  if ($previous_edit_id) {
    $row = evidence_get_edit ($previous_edit_id);
    $variant_id = $row["variant_id"];
  }
  else if ($variant_id)
    $row["variant_id"] = $variant_id;
  else if (!$row["variant_id"])
    die ("evidence_edit_id_generate(): need either previous edit id or variant id");

  $row["is_draft"] = 1;
  $row["is_delete"] = 0;
  $row["previous_edit_id"] = $previous_edit_id;
  $row["edit_oid"] = $_SESSION["user"]["oid"];
  unset ($row["edit_id"]);
  unset ($row["edit_timestamp"]);
  unset ($row["signoff_oid"]);
  unset ($row["signoff_timestamp"]);

  $sqlfields = "edit_timestamp=NOW()";
  $sqlparams = array();
  foreach ($row as $column => $value) {
    $sqlfields .= ", $column=?";
    $sqlparams[] = $value;
  }
  theDb()->query ("INSERT INTO edits SET $sqlfields", $sqlparams);
  $new_edit_id = theDb()->getOne ("SELECT LAST_INSERT_ID()");
  return theDb()->getRow ("SELECT * FROM edits WHERE edit_id=?",
			  array ($new_edit_id));
}

function evidence_save_draft ($edit_id, $newrow)
{
  if (!$edit_id && array_key_exists ("edit_id", $newrow))
    $edit_id = $newrow["edit_id"];

  $stmt = "UPDATE edits SET edit_timestamp=now()";
  $params = array();

  foreach (array ("variant_impact",
		  "variant_dominance",
		  "summary_short",
		  "summary_long",
		  "talk_text",
		  "article_pmid") as $field)
    {
      $stmt .= ", $field=?";
      $params[] = $newrow[$field];
    }
  $stmt .= " WHERE edit_id=? AND edit_oid=? AND is_draft=1";
  $params[] = $edit_id;
  $params[] = $_SESSION["user"]["oid"];
  
  if (!theDb()->query ($stmt, $params))
    {
      // TODO: report error
      die ("evidence_save_draft ($edit_id) failed: query failed");
      return false;
    }
  if (theDb()->affectedRows() != 1)
    {
      // TODO: report error
      die ("evidence_save_draft ($edit_id) failed: row not updated");
      return false;
    }
  return $edit_id;
}

function evidence_submit ($edit_id)
{
  // Push this edit to the "latest" snapshot (and un-mark its "draft"
  // flag so it shows up in edit history).

  // TODO: Check for conflicts (i.e. "snap_latest" version !=
  // previous_edit_id, or snap_latest has same variant+article+genome
  // key but previous_edit_id is null) -- whether caused by a race, or
  // by editing/saving an old revision) and force the user to
  // explicitly lose the intervening changes.

  theDb()->query ("UPDATE edits
 SET is_draft=0,
 edit_timestamp=NOW(),
 article_pmid=if(article_pmid is null,0,article_pmid),
 genome_id=if(genome_id is null,0,genome_id),
 disease_id=if(disease_id is null,0,disease_id)
 WHERE edit_id=? AND edit_oid=?",
		  array($edit_id, getCurrentUser("oid")));
  theDb()->query ("REPLACE INTO snap_latest
 SELECT *
 FROM edits
 WHERE edit_id=?
 AND edit_oid=?",
		  array($edit_id, getCurrentUser("oid")));
  theDb()->query ("DELETE FROM snap_latest WHERE is_delete=1");

  $v = theDb()->getOne ("SELECT variant_id FROM snap_latest WHERE edit_id=?",
			array ($edit_id));
  if ($v) {
    evidence_update_flat_summary ($v);
  }
  else {
    $v = theDb()->getOne ("SELECT variant_id FROM edits WHERE edit_id=?",
			  array ($edit_id));
    theDb()->query ("DELETE FROM flat_summary WHERE variant_id=?",
		    array ($v));
  }
}

function evidence_update_flat_summary ($variant_id)
{
  $flat = evidence_get_assoc_flat_summary ("latest", $variant_id);
  theDb()->query ("REPLACE INTO flat_summary
			SET variant_id=?, flat_summary=?,
			autoscore=?, webscore=?, n_genomes=?",
		  array ($variant_id,
			 json_encode ($flat),
			 $flat["autoscore"],
			 $flat["webscore"],
			 $flat["n_genomes"]));
}

function evidence_signoff ($edit_id)
{
  // Push this edit to the "release" snapshot.

  if (!$_SESSION["user"]["is_admin"])
    {
      // TODO: proper error reporting
      die ("only admin can do this");
    }

  theDb()->query ("UPDATE edits SET signoff_oid=?, signoff_timestamp=NOW()
		   WHERE edit_id=? AND signoff_oid IS NULL",
		  array ($_SESSION["user"]["oid"], $edit_id));
  $latest_signedoff_edit =
    theDb()->getOne ("SELECT edit_id FROM edits
		      WHERE variant_id = (SELECT variant_id FROM edits WHERE edit_id=?)
		      ORDER BY edit_timestamp DESC LIMIT 1",
		     $edit_id);
  if ($latest_signedoff_edit != $edit_id)
    {
      // TODO: proper error reporting
      die ("A more recent edit ($latest_signedoff_edit) has already been signed off.");
    }
  theDb()->query ("REPLACE INTO snap_release SELECT * FROM edits WHERE edit_id=?",
		  $edit_id);
}

function evidence_get_report ($snap, $variant_id)
{
  $flag_edited_id = 0;
  if ($snap == "latest" || $snap == "release") {
    $table = "snap_$snap";
    $and_max_edit_id = "";
  }
  else if (ereg ("^[0-9]+$", $snap)) {
    $flag_edited_id = $snap;
    $table = "edits";
    $variant_id = 0 + $variant_id;
    $and_max_edit_id = "AND $table.edit_id IN (
 SELECT MAX(edits.edit_id)
 FROM edits
 WHERE variant_id=$variant_id
 AND edit_id<=$snap
 AND is_draft=0
 GROUP BY article_pmid, genome_id, disease_id)
 AND ($table.edit_id=$snap OR $table.is_delete=0
 )";
  }

  // Get all items relating to the given variant

  $v =& theDb()->getAll ("SELECT variants.*, $table.*, genomes.*, datasets.*, variant_occurs.*,
			variants.variant_id AS variant_id,
			$table.genome_id AS genome_id,
			$table.disease_id AS disease_id,
			genetests.testable AS genetests_testable,
			genetests.reviewed AS genetests_reviewed,
			diseases.disease_name AS disease_name,
			variant_occurs.chr AS chr,
			variant_occurs.chr_pos AS chr_pos,
			variant_occurs.allele AS allele,
			variant_occurs.rsid AS rsid,
			vf.num AS variant_f_num,
			vf.denom AS variant_f_denom,
			vf.f AS variant_f,
			COUNT(datasets.dataset_id) AS dataset_count,
			MAX(zygosity) AS zygosity,
			MAX(dataset_url) AS dataset_url,
			MIN(dataset_url) AS dataset_url_2,
			$table.edit_id=? AS flag_edited_id
			FROM variants
			LEFT JOIN $table
				ON variants.variant_id = $table.variant_id
			LEFT JOIN genetests
				ON $table.disease_id=0
				AND $table.article_pmid=0
				AND $table.genome_id=0
				AND variants.variant_gene = genetests.gene
			LEFT JOIN diseases
				ON $table.disease_id = diseases.disease_id
			LEFT JOIN genomes
				ON $table.genome_id > 0
				AND $table.genome_id = genomes.genome_id
			LEFT JOIN datasets
				ON datasets.genome_id = $table.genome_id
			LEFT JOIN variant_occurs
				ON $table.variant_id = variant_occurs.variant_id
				AND variant_occurs.dataset_id = datasets.dataset_id
			LEFT JOIN variant_frequency vf
				ON vf.variant_id=variants.variant_id
			WHERE variants.variant_id=?
				$and_max_edit_id
			GROUP BY
				$table.genome_id,
				$table.article_pmid,
				$table.disease_id
			ORDER BY
				$table.genome_id,
				$table.article_pmid,
				diseases.disease_name,
				$table.disease_id,
				$table.edit_id DESC",
			 array ($flag_edited_id, $variant_id));
  if (theDb()->isError($v)) die ($v->getMessage());
  if (!theDb()->isError($v) && $v && $v[0])
    foreach (array ("article_pmid", "genome_id", "disease_id") as $x)
      if (!$v[0][$x])
	$v[0][$x] = 0;

  // Make sure for every pmid>0 row all of the article=A, disease=D
  // rows are there too (and ditto for article=0, genome=0)

  $have_a_d = array();		// will contain one array per article
				// id (incl. "0" for the main variant
				// summary section)
  foreach ($v as $row) {
    if (!$row["genome_id"]) {
      $have_a_d[$row["article_pmid"]][$row["disease_id"]] = 1;
    }
  }

  // Get a list of all the diseases that should be listed in each disease table

  $v_d = theDb()->getAll ("SELECT diseases.* FROM diseases
 WHERE disease_id IN
 (SELECT disease_id
  FROM variant_disease
  WHERE variant_id=?
 UNION
  SELECT disease_id
  FROM gene_disease
 WHERE gene = ?)", array ($variant_id, $v[0]["variant_gene"]));

  // Look for article=A, disease=D rows that should be there but
  // aren't... and add them

  foreach ($v_d as $row) {		   // for each disease...
    foreach ($have_a_d as $a => $have_d) { // for each article...
      $d = $row["disease_id"];
      if (isset ($have_d[$d]))
	// already have a result row for this {article, disease}
	continue;

      // add a row after all of the existing rows pertaining to the
      // target article

      for ($i=0; $i<sizeof($v); $i++) {
	// skip until we reach the target article's row
	if ($v[$i]["article_pmid"] != $a)
	  continue;
	// skip until we reach the last row for the target article
	if ($i<sizeof($v)-1 &&
	    $a != 0 &&
	    $v[$i+1]["article_pmid"] == $a)
	  continue;

	// found the last row for this article.  copy the existing row
	// (minus the editable stuff) and insert after.
	array_splice ($v, $i+1, 0, array($v[$i]));
	$v[$i+1]["disease_id"] = $row["disease_id"];
	$v[$i+1]["disease_name"] = $row["disease_name"];
	$v[$i+1]["summary_short"] = "";
	$v[$i+1]["summary_long"] = "";
	$v[$i+1]["talk_text"] = "";
	$v[$i+1]["edit_id"] = "";
	$v[$i+1]["previous_edit_id"] = "";
	break;
      }
    }
  }

  if ($v && is_array ($v[0])) {
    if (1) {
      // fix up obsolete impacts (until they get fixed in the db, at which
      // point this section can be removed)
      if ($v[0]["variant_impact"] == "unknown" ||
	  $v[0]["variant_impact"] == "none")
	$v[0]["variant_impact"] = "not reviewed";
    }

    $v[0]["certainty"]
	= evidence_compute_certainty ($v[0]["variant_quality"],
				      $v[0]["variant_impact"]);
    $v[0]["qualified_impact"]
	= evidence_qualify_impact ($v[0]["variant_quality"],
				   $v[0]["variant_impact"]);
    list ($v[0]["variant_evidence"], $v[0]["clinical_importance"])
	= str_split ($v[0]["certainty"]);
  }

  if ($v && is_array ($v[0])) {
    $row =& $v[0];

    $row["nblosum100"] = 0-blosum100($row["variant_aa_from"], $row["variant_aa_to"]);

    $tags = array();
    foreach (theDb()->getAll ("SELECT distinct tag FROM variant_external WHERE variant_id=?", array ($variant_id)) as $tagrow) {
      $tags[] = $tagrow["tag"];
    }
    $row["in_omim"] = in_array ("OMIM", $tags) ? 'Y' : '-';
    $row["in_gwas"] = in_array ("GWAS", $tags) ? 'Y' : '-';
    $row["in_pharmgkb"] = in_array ("PharmGKB", $tags) ? 'Y' : '-';

    $autoscore = 0;
    $why = array();

    // Computational (max of 2 points):
    if ($row["nblosum100"] > 9) { $autoscore+=2; $why[] = "nblosum100>9"; }
    else if ($row["nblosum100"] > 3) { $autoscore++; $why[] = "nblosum100>3"; }
    // TODO: ++$autoscore if within 1 base of a splice site
    // TODO: ++$autoscore if indel in coding region
    // TODO: ++$autoscore if indel in coding region and causes frameshift
    if ($autoscore > 2) $autoscore = 2;

    // Variant-specific lists (max of 2 points):
    $autoscore_db = 0;
    if ($row["in_omim"] == 'Y') { $autoscore_db+=2; $why[] = "omim"; }
    if ($row["in_gwas"] == 'Y') {
      $autoscore_db++; $why[] = "gwas";
      if ($row["gwas_max_or"] >= 1.5) {
	$autoscore_db++; $why[] = "gwas_or";
      }
    }
    if ($row["in_pharmgkb"] == 'Y') { ++$autoscore_db; $why[] = "PharmGKB"; }
    if ($autoscore_db > 2) $autoscore_db = 2;
    $autoscore += $autoscore_db;

    // Gene-specific lists (max of 2 points):
    if ($row["genetests_testable"]) { $autoscore++; $why[] = "genetest"; }
    if ($row["genetests_reviewed"]) { $autoscore++; $why[] = "genereview"; }

    $row["autoscore"] = $autoscore;
    $row["autoscore_flags"] = implode(", ",$why);

    // Summarize relevant/not-relevant votes as one of { null, 0, 1 }
    $row["webscore"] = "N";
    $urlscores =& evidence_get_web_votes ($variant_id);
    foreach (evidence_extract_urls (theDb()->getOne ("SELECT content FROM variant_external WHERE variant_id=? AND tag=?",
						     array ($variant_id, "Yahoo!"))) as $url) {
      if ($urlscores[$url] == 1) {
	$row["webscore"] = "Y";
	break;
      }
      if (!strlen ($urlscores[$url])) {
	$row["webscore"] = "-";
      }
    }
  }

  return $v;
}

$gWantKeysForAssoc = array
    ("all" => "edit_id previous_edit_id editor_name edit_timestamp signoff_oid signoff_timestamp",
     "disease" => "disease_id disease_name case_pos case_neg control_pos control_neg",
     "article" => "article_pmid summary_long",
     "genome" => "genome_id global_human_id name sex zygosity dataset_id rsid chr chr_pos allele summary_long",
     "variant" => "variant_id:id variant_gene:gene aa_change aa_change_short variant_rsid:rsid variant_impact:impact qualified_impact variant_dominance:inheritance quality_scores quality_comments variant_f_num variant_f_denom variant_f gwas_max_or nblosum100 disease_max_or variant_evidence clinical_importance genetests_testable genetests_reviewed in_omim in_gwas in_pharmgkb autoscore webscore");

function evidence_get_assoc ($snap, $variant_id)
{
  $rows =& evidence_get_report ($snap, $variant_id);

  global $gWantKeysForAssoc;
  if (!is_array ($gWantKeysForAssoc["variant"])) {
    foreach ($gWantKeysForAssoc as $k => &$v) {
      if ($k == "all") continue;
      $v = explode (" ", $gWantKeysForAssoc["all"] . " " . $v);
    }
  }

  $variant = array ("genomes" => array(),
		    "articles" => array(),
		    "diseases" => array());
  for ($i=0; $i<sizeof($rows); $i++) {
    $row =& $rows[$i];

    $editor = user::lookup ($row["edit_oid"]);
    $row["editor_name"] = $editor->get("fullname");

    if (strlen($row["summary_long"]) == 0)
      $row["summary_long"] = $row["summary_short"];

    if ($row["article_pmid"] > 0) {
      $section =& $variant["articles"]["".$row["article_pmid"]];
      $want_keys =& $gWantKeysForAssoc["article"];
    }
    else if ($row["genome_id"] > 0) {
      $section =& $variant["genomes"]["".$row["genome_id"]];
      $want_keys =& $gWantKeysForAssoc["genome"];
    }
    else {
      $section =& $variant;
      $want_keys =& $gWantKeysForAssoc["variant"];
      $row["aa_change"]
	  = $row["variant_aa_from"]
	  . $row["variant_aa_pos"]
	  . $row["variant_aa_to"];
      $row["aa_change_short"] = aa_short_form ($row["aa_change"]);

      // TODO: combine these into one array and add labels
      $row["quality_scores"] = str_split (str_pad ($row["variant_quality"], 6, "-"));
      $row["quality_comments"] = $row["variant_quality_text"] ? json_decode ($row["variant_quality_text"], true) : array();
      $diseases = evidence_get_all_oddsratios ($rows);
      unset ($max_or_id);
      foreach ($diseases as $id => &$d) {
	if (!isset ($max_or_id) ||
	    $diseases[$max_or_id]["figs"]["or"] < $d["figs"]["or"])
	  $max_or_id = $id;
      }
      if (isset ($max_or_id))
	$row["disease_max_or"] = $diseases[$max_or_id];
    }

    if ($row["disease_id"] > 0) {
      $section =& $section["diseases"]["".$row["disease_id"]];
      if (ereg ('^\[', $row["summary_short"]))
	$row = array_merge (json_decode ($row["summary_short"], true), $row);
      $want_keys =& $gWantKeysForAssoc["disease"];
    }

    foreach ($want_keys as $k) {
      list ($inkey, $outkey) = explode (":", $k);
      if (!$outkey) $outkey = $inkey;
      $section[$outkey] = $row[$inkey];
    }

    unset ($section);
  }

  foreach (array ("articles", "genomes") as $section) {
    $variant[$section] = array_values ($variant[$section]);
    foreach ($variant[$section] as &$x) {
      if (is_array ($x["diseases"]))
	$x["diseases"] = array_values ($x["diseases"]);
    }
  }

  return $variant;
}

function evidence_get_assoc_flat_summary ($snap, $variant_id)
{
  $nonflat =& evidence_get_assoc ($snap, $variant_id);
  $flat = array ();
  foreach (array ("gene", "aa_change", "aa_change_short", "rsid", "impact", "qualified_impact", "inheritance") as $k)
      $flat[$k] = $nonflat[$k];
  $flat["dbsnp_id"] = "";
  foreach ($nonflat["genomes"] as &$g) {
    if ($g["rsid"] > 0) {
      $flat["dbsnp_id"] = "rs".$g["rsid"];
      break;
    }
  }
  $flat["overall_frequency_n"] = $nonflat["variant_f_num"];
  $flat["overall_frequency_d"] = $nonflat["variant_f_denom"];
  $flat["overall_frequency"] = $nonflat["variant_f"];
  $flat["gwas_max_or"] = $nonflat["gwas_max_or"];
  $flat["n_genomes"] = sizeof ($nonflat["genomes"]);
  $flat["n_genomes_annotated"] = 0;
  $flat["n_haplomes"] = 0;
  foreach ($nonflat["genomes"] as &$g) {
    if (strlen ($g["summary_long"]) > 0)
      $flat["n_genomes_annotated"] ++;

    if ($g["zygosity"] != "homozygous" ||
	($g["sex"] == "M" && ($g["chr"] == "chrX" || $g["chr"] == "chrY")))
      $flat["n_haplomes"] ++;
    else
      $flat["n_haplomes"] += 2;
  }
  $flat["n_articles"] = sizeof ($nonflat["articles"]);
  $flat["n_articles_annotated"] = 0;
  foreach ($nonflat["articles"] as &$g) {
    if (strlen ($g["summary_long"]) > 0)
      $flat["n_articles_annotated"] ++;
  }
  $i = -1;
  foreach (array ("in_silico", "in_vitro", "case_control", "familial", "severity", "treatability") as $scoreaxis) {
    ++$i;
    if (sizeof ($nonflat["quality_scores"]) >= $i+1) {
      $flat["qualityscore_".$scoreaxis] = $nonflat["quality_scores"][$i];
    }
    else
      $flat["qualityscore_".$scoreaxis] = "-";
    if (sizeof ($nonflat["quality_scores"]) >= $i+1 &&
	strlen ($nonflat["quality_comments"][$i]["text"]) > 0) {
      $flat["qualitycomment_".$scoreaxis] = "Y";
    }
    else
      $flat["qualitycomment_".$scoreaxis] = "-";
  }

  $flat["gene_in_genetests"]
      = theDb()->getOne ("SELECT 1 FROM gene_disease WHERE gene=? LIMIT 1",
			 array ($flat["gene"])) ? 'Y' : '-';

  $flat["in_omim"] = $nonflat["in_omim"];
  $flat["in_gwas"] = $nonflat["in_gwas"];
  $flat["in_pharmgkb"] = $nonflat["in_pharmgkb"];
  $flat["genetests_testable"] = $nonflat["genetests_testable"] ? 'Y' : '-';
  $flat["genetests_reviewed"] = $nonflat["genetests_reviewed"] ? 'Y' : '-';
  $flat["nblosum100"] = $nonflat["nblosum100"];
  $flat["nblosum100>3"] = $nonflat["nblosum100"] > 3 ? 'Y' : '-';
  if ($nonflat["disease_max_or"]) {
    $flat["max_or_disease_name"] = $nonflat["disease_max_or"]["disease_name"];
    foreach (array ("case_pos", "case_neg", "control_pos", "control_neg", "or")
	     as $f)
      $flat["max_or_".$f] = $nonflat["disease_max_or"]["figs"][$f];
  }
  else {
    $flat["max_or_disease_name"] = "";
    foreach (array ("case_pos", "case_neg", "control_pos", "control_neg", "or")
	     as $f)
      $flat["max_or_".$f] = "";
  }

  $flat["autoscore"] = $nonflat["autoscore"];
  $flat["webscore"] = $nonflat["webscore"];
  $flat["variant_evidence"] = $nonflat["variant_evidence"];
  $flat["clinical_importance"] = $nonflat["clinical_importance"];
  return $flat;
}

function evidence_get_latest_edit ($variant_id,
				   $article_pmid, $genome_id, $disease_id,
				   $create_flag=false,
				   $defaults=false)
{
  if (!$variant_id) return null;
  $edit_id = theDb()->getOne
    ("SELECT MAX(edit_id) FROM edits
	WHERE variant_id=? AND article_pmid=? AND genome_id=? AND disease_id=?
	AND (is_draft=0 OR edit_oid=?)",
     array ($variant_id, $article_pmid, $genome_id, $disease_id,
	    getCurrentUser("oid")));

  if ($edit_id &&
      theDb()->getOne ("SELECT is_delete FROM edits WHERE edit_id=?",
		       array ($edit_id)))
    $edit_id = FALSE;

  if (!$edit_id && $create_flag) {
    $sql = "INSERT INTO edits
	SET edit_timestamp=NOW(), edit_oid=?, is_draft=1,
	variant_id=?, article_pmid=?, genome_id=?, disease_id=?";
    $params = array (getCurrentUser("oid"),
		     $variant_id, $article_pmid, $genome_id, $disease_id);
    if (is_array ($defaults)) {
      foreach ($defaults as $col => $value) {
	$sql .= ", $col=?";
	$params[] = $value;
      }
    }
    theDb()->query ($sql, $params);
    $edit_id = theDb()->getOne ("SELECT LAST_INSERT_ID()");
    evidence_submit ($edit_id);
  }
  return $edit_id + 0;
}

class evidence_row_renderer {
    protected $lastrow = FALSE;
    protected $rownumber = FALSE;
    protected $html = "";
    protected $starttable = "";

    protected function row_transition (&$row)
    {
	if ($this->lastrow &&
	    $this->lastrow["disease_id"] &&
	    $this->rownumber > 0 &&
	    (!$row ||
	     $this->lastrow["article_pmid"] != $row["article_pmid"] ||
	     $this->lastrow["genome_id"] != $row["genome_id"]))
	  $this->html .= "</TABLE><P>&nbsp;</P>\n";
	if ($row &&
	    $row["disease_id"] &&
	    (!$this->lastrow ||
	     !$this->lastrow["disease_id"] ||
	     $row["article_pmid"] != $this->lastrow["article_pmid"] ||
	     $row["genome_id"] != $this->lastrow["genome_id"])) {
	  $class2 = "";
	  $title = "Cases/controls";
	  if ($row["article_pmid"] === "*" &&
	      $row["genome_id"] === "*") {
	    $class2 = " disease_totals";
	    $title = "<STRONG>Total cases/controls</STRONG>";
	  }
	  else if ($row["article_pmid"] == "0" &&
		   $row["genome_id"] == "0") {
	    $title = "Unpublished cases/controls";
	  }
	  $class3 = " delete_with_v{$row[variant_id]}_a{$row[article_pmid]}_g{$row[genome_id]}";
	  $this->starttable = "<TABLE $id class=\"disease_table$class2$class3\">\n";
	  $this->starttable .= "<TR><TH class=\"rowlabel\">$title</TH>";
	  foreach (array ("case+", "case&ndash;", "control+", "control&ndash;", "odds&nbsp;ratio") as $x)
	    $this->starttable .= "<TH width=\"60\">&nbsp;$x</TH>";
	  $this->starttable .= "</TR>\n";
	  $this->rownumber = 0;
	}
	$this->lastrow = $row;
    }

    function &html ()
    {
	$this->row_transition($x=FALSE);
	return $this->html;
    }

    function render_row (&$row)
    {
	global $gDisableEditing;
	$html = "";

	$this->row_transition ($row);
	$id_prefix = "v_$row[variant_id]__a_$row[article_pmid]__g_$row[genome_id]__d_$row[disease_id]__p_$row[edit_id]__";
	$title = "";

	foreach (array ("article_pmid", "genome_id", "disease_id") as $keyfield) {
	  if (strlen ($row[$keyfield]) == 0)
	    $row[$keyfield] = 0;
	}

	if ($row["disease_id"] != "0") {
	  $tr = editable ("${id_prefix}f_summary_short__8x1__oddsratio",
			  $row["summary_short"],
			  $row["disease_name"] . "<BR />",
			  array ("rownumber" => $this->rownumber,
				 "tip" => "Indicate the contribution of this article to OR statistics for ".htmlspecialchars($row["disease_name"])."."));
	  if ($tr != "") {
	    if ($this->rownumber == 0)
	      $html .= $this->starttable;
	    $html .= $tr;
	    ++$this->rownumber;
	  }
	}

	else if ($row["article_pmid"] != "0") {
	  $html .= "<A name=\"a".htmlentities($row["article_pmid"])."\"></A>\n";
	  $summary = article_get_summary ($row["article_pmid"]);
	  $html .= editable ("${id_prefix}f_summary_short__70x8__textile",
			     $row[summary_short],
			     $summary . "<BR />",
			     array ("tip" => "Explain this article's contribution to the conclusions drawn in the variant summary above."));
	}

	else if ($row["genome_id"] != "0") {

	  $html .= "<A name=\"g".$row["genome_id"]."\"></A>\n";

	  // Pick the most human-readable name for this genome/person
	  if (!($name = $row["name"]))
	    if (!($name = $row["global_human_id"]))
	      $name = "[" . $row["genome_id"] . "]";
	  $name = htmlspecialchars ($name);

	  // Link to the full genome(s)
	  if ($row["dataset_count"] > 0)
	    $name = "<A href=\"$row[dataset_url]\">$name</A>";
	  if ($row["dataset_count"] > 1) {
	    $more = $row["dataset_count"] - 1;
	    $name .= " (";
	    if ($row["dataset_url_2"]) {
	      $name .= "<A href=\"$row[dataset_url_2]\">alternate</A>, ";
	      --$more;
	    }
	    if ($more > 1)
	      $name .= "plus $more other data sets";
	    else if ($more == 1)
	      $name .= "plus 1 other data set";
	    else
	      $name = ereg_replace (", $", "", $name);
	    $name .= ")";
	  }

	  // Indicate the SNP that causes the variant
	  if ($row["chr"]) {
	    $name .= htmlspecialchars ("\n".substr($row["zygosity"],0,3)." ".$row["allele"]." @ ".$row["chr"].":".$row["chr_pos"]);
	    $name = nl2br ($name);
	  }

	  $html .= editable ("${id_prefix}f_summary_short__70x8__textile",
			     $row[summary_short],
			     $name);
	}

	else if ($row["disease_id"] != "0") {
	  // Disease summary not attached to any particular publication
	}

	else {
	  $html .= editable ("${id_prefix}f_summary_short__70x8__textile",
			     $row[summary_short],
			     "Short summary",
			     array ("tip" => "Provide a one line summary of clinical action to be undertaken given this variant (possibly modified by known phenotypes)."));

	  $html .= editable ("${id_prefix}f_variant_quality",
			     $row,
			     "Variant quality");

	  global $gImpactOptions;
	  $opts =& $gImpactOptions;
	  $qualified_impact = evidence_qualify_impact ($row["variant_quality"],
						       $row["variant_impact"]);
	  $html .= editable ("${id_prefix}f_variant_impact__",
			     $row["variant_impact"],
			     "Impact",
			     array ("select_options"
				    => $opts,
				    "previewtextile" => $qualified_impact,
				    "tip" => "Categorize the expected impact of this variant."));

	  if (strtolower ($qualified_impact) != strtolower ($row["variant_impact"])) {
	    $html .= "<P><I>(The \"".strtolower (eregi_replace (",? ".$row["variant_impact"], "", $qualified_impact))."\" qualifier is assigned automatically based on the above evidence and importance scores.)</I></P>";
	  }

	  global $gInheritanceOptions;
	  $html .= editable ("${id_prefix}f_variant_dominance__",
			     $row[variant_dominance],
			     "Inheritance pattern",
			     array ("select_options" => $gInheritanceOptions));
	  $html .= editable ("${id_prefix}f_summary_long__70x8__textile",
			     $row[summary_long],
			     "Summary of published research, and additional commentary",
			     array ("tip" => "Provide a comprehensive review of the variant including youngest age of onset, oldest age of onset and oldest asymptomatic individual."));
	}

	if ($html == "")
	  return;

	if (ereg ('^<(TABLE|TR)', $html)) {
	  // TODO: handle is_delete and flag_edited_id for table rows
	  // somehow; for now just don't indicate them at all
	  if ($row["is_delete"]) return;
	  if ($row["flag_edited_id"]) { $this->html .= $html; return; }
	}

	else if (!$gDisableEditing && getCurrentUser() ||
		 0 < strlen ($row["talk_text"])) {
	  $show_label = 0 < strlen ($row["talk_text"]) ? "<B>show discussion</B>" : "start discussion";
	  $html .= "<DIV class=\"rectangle-speech-border-hidden\"><DIV>$show_label</DIV>";
	  $html .= editable ("${id_prefix}f_talk_text__70x8__textile",
			     $row[talk_text],
			     "Discussion<BR />",
			     array ("tip" => "Comments about this section"));
	  $html .= "</DIV>";
	}

	if ($row["is_delete"])
	  $html .= "<DIV style=\"outline: 1px dashed #300; background-color: #fdd; color: #300; padding: 20px 20px 0 20px; margin: 0 0 10px 0;\"><P>Deleted in this revision:</P>$html</DIV>";
	else if ($row["flag_edited_id"]) {
	  if ($row["previous_edit_id"])
	    $edited = "Edited";
	  else
	    $edited = "Added";
	  $html = "<DIV style=\"outline: 1px dashed #300; background-color: #dfd; color: #300; padding: 20px 20px 0 20px; margin: 0 0 10px 0;\"><P>$edited in this revision:</P>\n$html</DIV>";
	}
	$this->html .= $html;
    }
}

function evidence_render_history ($variant_id)
{
  $html = "<UL>\n";
  $thisyear = strftime ("%Y", time());
  $today = strftime ("%b %e %Y", time());
  $q = theDb()->query ("
SELECT
 UNIX_TIMESTAMP(edit_timestamp) AS edit_timestamp,
 edit_oid,
 u.fullname AS edit_fullname,
 article_pmid,
 s.genome_id AS genome_id,
 s.disease_id AS disease_id,
 d.disease_name AS disease_name,
 IF(g.name IS NULL OR g.name='',concat('[',global_human_id,']'),g.name) AS genome_name,
 is_delete,
 previous_edit_id,
 edit_id,
 v.*
FROM edits s
LEFT JOIN variants v ON v.variant_id=s.variant_id
LEFT JOIN eb_users u ON u.oid=s.edit_oid
LEFT JOIN genomes g ON g.genome_id=s.genome_id
LEFT JOIN diseases d ON d.disease_id=s.disease_id
WHERE s.variant_id=? AND is_draft=0
ORDER BY edit_timestamp DESC, edit_id DESC, previous_edit_id DESC
",
		       array ($variant_id));
  if (theDb()->isError($q)) die ($q->getMessage());
  $lastli = "";
  while ($row =& $q->fetchRow()) {
    $li = "<LI>";

    $nicetime = strftime ("%b %e %Y %l:%M%P", $row["edit_timestamp"]);
    $nicetime = str_replace ("  ", " ", $nicetime);
    $nicetime = str_replace ("$today ", "", $nicetime);
    $nicetime = str_replace ("$thisyear ", "", $nicetime);
    $nicetime = ereg_replace (" [^ ]+$", "", $nicetime);
    $li .= $nicetime;

    $li .= " <A href=\"edits?oid=".urlencode($row["edit_oid"])."\">".htmlspecialchars($row["edit_fullname"])."</A> ";

    $summary = "";
    if ($row["is_delete"]) $li .= "removed ";
    else if ($row["previous_edit_id"]) { $li .= "edited "; $summary = " summary"; }
    else $li .= "added ";

    if ($row["disease_id"]) $li .= "OR figures for ".htmlspecialchars($row["disease_name"])." from ".($row["article_pmid"] ? "article ".$row["article_pmid"] : "unpublished research section");
    else if ($row["article_pmid"]) $li .= "article ".htmlspecialchars($row["article_pmid"]).$summary;
    else if ($row["genome_id"]) $li .= htmlspecialchars($row["genome_name"]).$summary;
    else $li .= "variant$summary";

    $variant_name = evidence_get_variant_name (&$row, "-");
    $li .= " <A href=\"".$variant_name.";".$row["edit_id"]."\">view</A>";

    $li .= "</LI>\n";

    // Compress sequences of same type of edit (same person, date, etc.)
    if ($li != $lastli)
      $html .= $li;
    else
      // TODO: offer to expand these in case the person wants to
      // recover an intermediate edit
      ;
    $lastli = $li;
  }
  $html .= "</UL>\n";
  return $html;
}


function evidence_get_variant_name (&$variant, $separator=" ", $shortp=false)
{
  if (is_array ($variant))
    $row =& $variant;
  else if (ereg ("^[0-9]+$", $variant))
    $row =& theDb()->getRow ("SELECT * from variants WHERE variant_id=?", array ($variant));

  if ($row["variant_rsid"])
    return "rs".$row["variant_rsid"];
  else if ($shortp)
    return $row["variant_gene"].$separator.aa_short_form ($row["variant_aa_from"].$row["variant_aa_pos"].$row["variant_aa_to"]);
  else
    return $row["variant_gene"].$separator.$row["variant_aa_from"].$row["variant_aa_pos"].$row["variant_aa_to"];
}


function evidence_get_all_oddsratios ($report)
{
  $disease = array ();
  foreach ($report as $row) {
    if (!(($id = $row["disease_id"]) > 0 &&
	  ereg ('^{', $row["summary_short"])))
      continue;
    $figs = json_decode ($row["summary_short"], true);
    if (!strlen ($figs["case_pos"]) &&
	!strlen ($figs["case_neg"]) &&
	!strlen ($figs["control_pos"]) &&
	!strlen ($figs["control_neg"]))
      continue;
    $disease[$id]["figs"]["case_pos"] += $figs["case_pos"];
    $disease[$id]["figs"]["case_neg"] += $figs["case_neg"];
    $disease[$id]["figs"]["control_pos"] += $figs["control_pos"];
    $disease[$id]["figs"]["control_neg"] += $figs["control_neg"];
    $disease[$id]["disease_id"] = $row["disease_id"];
    $disease[$id]["disease_name"] = $row["disease_name"];
    $disease[$id]["article_pmid"] = "*";
    $disease[$id]["genome_id"] = "*";
    $disease[$id]["figs"]["or"] = oddsratio_compute ($disease[$id]["figs"]);
  }
  return $disease;
}

function evidence_render_oddsratio_summary_table ($report)
{
  $disease =& evidence_get_all_oddsratios ($report);

  if (!sizeof ($disease))
    return "";

  global $gDisableEditing;
  $gDE_was = $gDisableEditing;
  $gDisableEditing = true;
  $renderer = new evidence_row_renderer;
  foreach ($disease as $id => &$row) {
    $row["summary_short"] = json_encode ($row["figs"]);
    $renderer->render_row ($row);
  }
  $gDisableEditing = $gDE_was;
  return $renderer->html();
}


function evidence_compute_certainty ($scores, $impact)
{
  // Summarize the given quality scores (in the context of the given
  // impact category) as a two-character string, the first character
  // representing variant evidence and the second character
  // representing clinical importance.  Each can be 0
  // (uncertain/unimportant), 1 (likely/important), 2 (well
  // established/very important), or "-" (not applicable).

  if ($impact == "not reviewed" || $impact == "unknown" || $impact == "none")
    return "--";

  $scores = str_split (str_pad ($scores, 6, "-"));
  foreach ($scores as $i => &$score)
      if ($score === "-") $score = 0;
      else if ($score === "!") $score = -1;

  $score_evidence = $scores[0]+$scores[1]+$scores[2]+$scores[3];
  if (($scores[2] >= 4 || $scores[3] >= 4) && $score_evidence >= 8)
    $certainty = "2";
  else if (($scores[2] >= 3 || $scores[3] >= 3) && $score_evidence >= 5)
    $certainty = "1";
  else
    $certainty = "0";

  if ($impact == "benign" || $impact == "protective")
    $certainty .= "-";
  else if ($scores[4] >= 4 || ($scores[4] >= 3 && $scores[5] >= 4))
    $certainty .= "2";
  else if ($scores[4] >= 3 || ($scores[4] >= 2 && $scores[5] >= 3))
    $certainty .= "1";
  else
    $certainty .= "0";

  return $certainty;
}


function evidence_qualify_impact ($scores, $impact)
{
  $c = str_split (evidence_compute_certainty ($scores, $impact));
  if ($c[0] === "-") return ucfirst ($impact);
  if ($c[0] === '0') $impact = "uncertain $impact";
  else if ($c[0] === '1') $impact = "likely $impact";
  if ($c[1] === '0') $impact = "low clinical importance, $impact";
  else if ($c[1] === '1') $impact = "moderate clinical importance, $impact";
  else if ($c[1] === '2') $impact = "high clinical importance, $impact";
  return ucfirst ($impact);
}


function evidence_get_web_votes ($variant_id)
{
  $results = array();
  $votes =& theDb()->getAll ("SELECT * FROM web_vote WHERE variant_id=?",
			     array ($variant_id));
  foreach ($votes as &$v) {
    if ($v["vote_0"] > $v["vote_1"]) // "no" votes win
      $result = 0;
    else if ($v["vote_1"] > 0)	// tie, or "yes" votes win
      $result = 1;
    else			// no votes yet
      $result = null;
    $results[$v["url"]] = $result;
  }
  return $results;
}


function evidence_get_my_web_vote ($variant_id)
{
  $myvotes = array();
  if (!($oid = getCurrentUser("oid")))
    return $myvotes;
  $votes =& theDb()->getAll ("SELECT * FROM web_vote_history
				WHERE variant_id=? AND vote_oid=?
				ORDER BY vote_timestamp DESC",
			     array ($variant_id, $oid));
  foreach ($votes as &$v) {
    if (!isset ($myvotes[$v["url"]]))
      $myvotes[$v["url"]] = $v["vote_score"];
  }
  return $myvotes;
}


function evidence_set_my_web_vote ($variant_id, $url, $score)
{
  if (!($oid = getCurrentUser("oid")))
    return;
  theDb()->query ("INSERT INTO web_vote_history SET
			variant_id=?, url=?, vote_oid=?, vote_score=?",
		  array ($variant_id, $url, $oid, $score));
  theDb()->query ("REPLACE INTO web_vote_latest SET
			variant_id=?, url=?, vote_oid=?, vote_score=?",
		  array ($variant_id, $url, $oid, $score));

  $current =& theDb()->getAll ("SELECT COUNT(*) c, vote_score
				FROM web_vote_latest
				WHERE variant_id=? AND url=?
				GROUP BY vote_score",
			       array ($variant_id, $url));
  $vote_0=0;
  $vote_1=0;
  foreach ($current as $c) {
    if ($c["vote_score"] == 1)
      $vote_1 += $c["c"];
    else if (strlen ($c["vote_score"]))
      $vote_0 += $c["c"];
  }
  theDb()->query ("REPLACE INTO web_vote SET variant_id=?, url=?, vote_0=?, vote_1=?",
		  array ($variant_id, $url, $vote_0, $vote_1));
  if (theDb()->affectedRows() > 0) {
    evidence_update_flat_summary ($variant_id);
  }
}


function evidence_extract_urls ($html)
{
  $urls = array();
  preg_match_all ('{<LI>.*?</LI>}is', $html, $matches,
		  PREG_PATTERN_ORDER);
  foreach ($matches[0] as $hit) {
    if (preg_match ('{<A href="(.*?)"}', $hit, $regs)) {
      $urls[] = htmlspecialchars_decode ($regs[1], ENT_QUOTES);
    }
  }
  return $urls;
}


function evidence_add_vote_tag_callback ($variant_id, $matches)
{
  global $evidence_current_votes;
  if (ereg ('^http://search.yahoo.com', $matches[1]))
    return $matches[0];
  $html = $matches[0];

  global $webvote_unique_id;
  ++$webvote_unique_id;

  $yes_image = "<img id=\"webvoter_all_$webvote_unique_id\" src=\"/img/thumbsup-32.png\" width=\"16\" height=\"16\" border=\"0\" valign=\"bottom\">";
  $no_image = "<img id=\"webvoter_all_$webvote_unique_id\" src=\"/img/thumbsdown-32.png\" width=\"16\" height=\"16\" border=\"0\" valign=\"bottom\">";
  $empty_image = "<img id=\"webvoter_all_$webvote_unique_id\" src=\"/img/thumbsup-32.png\" width=\"16\" height=\"16\" border=\"0\" valign=\"bottom\" style=\"display:none;\">";

  $url = htmlspecialchars_decode ($matches[1], ENT_QUOTES);
  if ($evidence_current_votes[$url] == 1)
    $html = $yes_image . "&nbsp;" . $html;
  else if (strlen ($evidence_current_votes[$url]))
    $html = $no_image . "&nbsp;" . $html;
  else if (getCurrentUser())
    $html = $empty_image . "&nbsp;" . $html;

  if (!getCurrentUser())
    return $html;

  return $html . "&nbsp;&nbsp;&nbsp;<a class=\"webvoter\" id=\"webvoter1_$webvote_unique_id\" onclick=\"return evidence_web_vote($variant_id,this,1);\" href=\"$matches[1]\">$yes_image</a>&nbsp;<a class=\"webvoter\" id=\"webvoter0_$webvote_unique_id\" onclick=\"return evidence_web_vote($variant_id,this,0);\" href=\"$matches[1]\">$no_image</a>";
}


function evidence_add_vote_tags ($variant_id, $html)
{
  global $evidence_current_votes;
  $evidence_current_votes = evidence_get_web_votes ($variant_id);
  return preg_replace_callback ('{<A href="(.*?)">.*?</A>}si',
				create_function ('$matches',
						 "return evidence_add_vote_tag_callback($variant_id, \$matches);"),
				$html);
}


$gInheritanceOptions = array
    ("dominant" => "dominant",
     "recessive" => "recessive",
     "other" => "other (e.g., modifier, co-dominant, incomplete penetrance)",
     "undefined" => "undefined in the literature",
     "unknown" => "unknown (literature unavailable or not yet reviewed)");

$gImpactOptions = array
    ("pathogenic" => "pathogenic",
     "benign" => "benign",
     "protective" => "protective",
     "pharmacogenetic" => "pharmacogenetic",
     "not reviewed" => "not reviewed");
?>
