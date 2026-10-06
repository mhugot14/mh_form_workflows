<?php

declare(strict_types=1);

namespace Mh\FormWorkflows\Model\Form;

/**
 * Optionaler Schritt der Absentismus-Eskalation: weiteres (3., 4., …)
 * Pädagogisches Gespräch nach dem 2. Gespräch. Gleiche Felder und Regeln wie
 * das 1. Gespräch (inkl. "extern dokumentiert") — nur eigener Slug, damit es in
 * der Fall-Regeltabelle unabhängig wiederholbar ist.
 */
class Absentismus_Step_Gespraech_Weiteres_Form extends Absentismus_Step_Gespraech_1_Form {

	public function get_slug(): string {
		return 'gespraech_weiteres';
	}
}
