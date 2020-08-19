-- +--------------------------------------------------------------------+
-- | Copyright CiviCRM LLC. All rights reserved.                        |
-- |                                                                    |
-- | This work is published under the GNU AGPLv3 license with some      |
-- | permitted exceptions and without any warranty. For full license    |
-- | and copyright information, see https://civicrm.org/licensing       |
-- +--------------------------------------------------------------------+
--
-- Generated from schema.tpl
-- DO NOT EDIT.  Generated by CRM_Core_CodeGen
--


-- +--------------------------------------------------------------------+
-- | Copyright CiviCRM LLC. All rights reserved.                        |
-- |                                                                    |
-- | This work is published under the GNU AGPLv3 license with some      |
-- | permitted exceptions and without any warranty. For full license    |
-- | and copyright information, see https://civicrm.org/licensing       |
-- +--------------------------------------------------------------------+
--
-- Generated from drop.tpl
-- DO NOT EDIT.  Generated by CRM_Core_CodeGen
--
-- /*******************************************************
-- *
-- * Clean up the exisiting tables
-- *
-- *******************************************************/

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `civicrm_strings`;

SET FOREIGN_KEY_CHECKS=1;
-- /*******************************************************
-- *
-- * Create new tables
-- *
-- *******************************************************/

-- /*******************************************************
-- *
-- * civicrm_strings
-- *
-- * FIXME
-- *
-- *******************************************************/
CREATE TABLE `civicrm_strings` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Unique Strings ID',
     `entity_table` varchar(64) NOT NULL   COMMENT 'Table where referenced item is stored',
     `entity_field` varchar(64) NOT NULL   COMMENT 'Field where referenced item is stored',
     `entity_id` int NOT NULL   COMMENT 'ID of the relevant entity.',
     `string` longtext NOT NULL   COMMENT 'Translated strinng',
     `language` varchar(16) NOT NULL   COMMENT 'Relevant language',
     `is_active` tinyint    COMMENT 'Is this string active?',
     `is_default` tinyint    COMMENT 'Is this the default string for the given locale?'
,
        PRIMARY KEY (`id`)



)    ;

