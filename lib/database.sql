-- phpMyAdmin SQL Dump
-- version 4.1.14
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Jun 07, 2015 at 12:56 AM
-- Server version: 5.6.17
-- PHP Version: 5.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `anode`
--

-- --------------------------------------------------------

--
-- Table structure for table `_permissions`
--

CREATE TABLE IF NOT EXISTS `_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `from` int(11) NOT NULL,
  `to` int(11) NOT NULL,
  `type` tinytext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;


-- --------------------------------------------------------

--
-- Table structure for table `_settings`
--

CREATE TABLE IF NOT EXISTS `_settings` (
  `parameter` tinytext NOT NULL,
  `value` text NOT NULL,
  UNIQUE KEY `parameter` (`parameter`(24))
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `_settings`
--

INSERT INTO `_settings` (`parameter`, `value`) VALUES
('salt', 'UonIckv6nzgsaEOklSoxhcE57UGVmjKyKhTTtE8dIiqhw90vXqmb84nLK5xGIiqhw90vXqmb84nLK5xG');

-- --------------------------------------------------------

--
-- Table structure for table `_types`
--

CREATE TABLE IF NOT EXISTS `_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `color` tinytext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `_users`
--

CREATE TABLE IF NOT EXISTS `_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` tinytext NOT NULL,
  `sphash` text NOT NULL,
  `email` varchar(255) NOT NULL,
  `phash` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniqueEmail` (`email`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=52 ;

