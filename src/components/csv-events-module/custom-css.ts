// WordPress dependencies.
import { __ } from '@wordpress/i18n';

import metadata from './module.json';

type CssFieldKey = 'heading' | 'controls' | 'content';
type CssFieldValue = { subName: string; selectorSuffix: string; label: string };
const source = metadata.customCssFields as Record<CssFieldKey, CssFieldValue>;

export const cssFields: Record<CssFieldKey, CssFieldValue> = {
  heading:  { ...source.heading, label: __('Heading', 'divi-csv-events') },
  controls: { ...source.controls, label: __('Controls', 'divi-csv-events') },
  content:  { ...source.content, label: __('Content', 'divi-csv-events') },
};
