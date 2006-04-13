<?php
/**
 * LaTeX Rendering Class - PHPBB Hook
 * Copyright (C) 2003  Benjamin Zeiss <zeiss@math.uni-goettingen.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 * --------------------------------------------------------------------
 * @author Benjamin Zeiss <zeiss@math.uni-goettingen.de>
 * @version v0.6
 * @package latexrender
 *
 */

function latex_content($text) {
     
    global $webDir, $urlAppend, $have_latex;

    if (!isset($have_latex) or !$have_latex) {
			return $text;
		}

    // --------------------------------------------------------------------------------------------------
    // adjust this to match your system configuration
    $latexrender_path = "$webDir"."/modules/latexrender";
    $latexrender_path_http = "$urlAppend"."/modules/latexrender";

    // --------------------------------------------------------------------------------------------------

    include_once($latexrender_path."/class.latexrender.php");

    preg_match_all("#\[tex\](.*?)\[/tex\]#si",$text,$tex_matches);

    $latex = new LatexRender($latexrender_path."/pictures",$latexrender_path_http."/pictures");

    for ($i=0; $i < count($tex_matches[0]); $i++) {
        $pos = strpos($text, $tex_matches[0][$i]);
        $latex_formula = $tex_matches[1][$i];
				$latex_formula = trim($latex_formula);
				if (substr($latex_formula, 0, 5) != '\frac') {
        	$latex_formula = "\n\n" .$latex_formula;
				}
	// replace tags & characters put in by htmlArea
        $latex_formula = str_replace("&amp;","&",$latex_formula);
        $latex_formula = str_replace("&nbsp;"," ",$latex_formula);
        $latex_formula = str_replace("<BR>","",$latex_formula);
        $latex_formula = str_replace("<P>","",$latex_formula);
        $latex_formula = str_replace("</P>","",$latex_formula);
	$url = $latex->getFormulaURL($latex_formula);

        if ($url != false) {
            $text = substr_replace($text, "<img src='".$url."' alt='".$latex_formula."' align=absmiddle>",$pos,strlen($tex_matches[0][$i]));
        } else {
            $text = substr_replace($text, "[unparseable or potentially dangerous latex formula]",$pos,strlen($tex_matches[0][$i]));
        }
    }
    return $text;
}

?>

