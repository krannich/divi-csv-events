// WordPress dependencies.
import { __ } from '@wordpress/i18n';

import metadata from './module.json';

const customCssFields = metadata.customCssFields as Record<'heading' | 'controls' | 'content', { subName: string, selectorSuffix: string, label: string }>;

customCssFields.heading.label  = __('Heading', 'divi-csv-events');
customCssFields.controls.label = __('Controls', 'divi-csv-events');
customCssFields.content.label  = __('Content', 'divi-csv-events');

export const cssFields = { ...customCssFields };
