-- phpMyAdmin SQL Dump
-- version 3.3.10.4
-- http://www.phpmyadmin.net
--
-- Host: mysql.yuhaoims.com
-- Generation Time: Aug 06, 2015 at 09:45 AM
-- Server version: 5.1.56
-- PHP Version: 5.5.26

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `ims_v2`
--

-- --------------------------------------------------------

--
-- Table structure for table `sku_standard`
--

CREATE TABLE IF NOT EXISTS `sku_standard` (
  `sku_category` varchar(64) NOT NULL,
  `sku_code` varchar(64) NOT NULL,
  `description` varchar(256) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Dumping data for table `sku_standard`
--

INSERT INTO `sku_standard` (`sku_category`, `sku_code`, `description`) VALUES
('garment', 'TS', 'T-Shirt'),
('garment', 'TT', 'Tank Top'),
('garment', 'JR', 'Jersey'),
('garment', 'ON', 'Jumpsuit'),
('garment', 'JK', 'Jacket'),
('garment', 'HD', 'Zip-Up Hoodie'),
('garment', 'LG', 'Leggings'),
('garment', 'FP', 'Fannypack'),
('garment', 'DB', 'Duffel Bag'),
('garment', 'DR', 'Dress'),
('garment', 'DS', 'Dress Shirt'),
('garment', 'CT', 'Crop Top'),
('garment', 'BF', 'Briefs'),
('garment', 'BS', 'Bodysuit'),
('garment', 'BD', 'Boardshorts'),
('garment', 'SS', 'Crewneck Sweatshirt'),
('garment', 'BL', 'Blouse'),
('garment', 'HT', 'Hat'),
('garment', 'KC', 'Keychain'),
('garment', 'BN', 'Beanie'),
('garment', 'BP', 'Backpack'),
('garment', 'SH', 'Shorts'),
('garment', 'PO', 'Poncho'),
('garment', 'SG', 'Sunglasses'),
('garment', 'SW', 'Swimwear'),
('garment', 'SN', 'Snood'),
('garment', 'WB', 'Wristband'),
('garment', 'AS', 'Ankle Socks'),
('garment', 'WT', 'Watches'),
('garment', 'SP', 'Sweatpants'),
('garment', 'UW', 'Underwear'),
('garment', 'PC', 'Phonecase'),
('garment', 'PT', 'Pants'),
('garment', 'SK', 'Skirts'),
('garment', 'ST', 'Sticker'),
('garment', 'BK', 'Bikini'),
('garment', 'KH', 'Knee High Socks'),
('garment', 'SM', 'Swimsuit'),
('garment', 'BA', 'Bandana'),
('garment', 'BT', 'Beach Towel'),
('garment', 'BW', 'Pillow Case'),
('garment', 'BG', 'Duvet Cover'),
('garment', 'CM', 'Coffee Mug'),
('garment', 'CA', 'Apron'),
('garment', 'FF', 'Flip Flops'),
('garment', 'IS', 'iSlides'),
('garment', 'SC', 'Shower Curtain'),
('garment', 'SL', 'Couch Pillow'),
('garment', 'HP', 'Hydropack'),
('garment', 'YM', 'Yoga Mat'),
('garment', 'MP', 'Mystery Pack'),
('garment', 'EX', 'AliProduct'),
('garment', 'FB', 'Fleece Blanket'),
('garment', 'BJ', 'Baseball Jacket'),
('garment', 'CD', 'Cut Dress'),
('garment', 'CK', 'Circle Skirt'),
('garment', 'DD', 'Dungaree'),
('garment', 'GB', 'String Bottom'),
('garment', 'ID', 'Circle Dress'),
('garment', 'LS', 'Long Sleeve Shirt'),
('garment', 'OD', 'Off Dress'),
('garment', 'PB', 'Padded Bandeau Top'),
('garment', 'SD', 'Simple Dress'),
('garment', 'SJ', 'Snowboard Jacket'),
('garment', 'TR', 'Triangle Top'),
('garment', 'WJ', 'Women''s Jacket');