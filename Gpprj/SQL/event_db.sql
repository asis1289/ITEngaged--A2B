-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 28, 2025 at 09:09 AM
-- Server version: 8.0.31
-- PHP Version: 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


/* creating  database*/

Drop database if exists event_db;
create database event_db;
use event_db;

-- Table structure for table `admin_replies`
--
DROP TABLE IF EXISTS `admin_replies`;
CREATE TABLE IF NOT EXISTS `admin_replies` (
  `reply_id` int NOT NULL AUTO_INCREMENT,
  `reference_type` enum('enquiry','feedback') NOT NULL,
  `reference_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `system_admin_id` int NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `read_status` tinyint DEFAULT '0',
  PRIMARY KEY (`reply_id`),
  KEY `user_id` (`user_id`),
  KEY `system_admin_id` (`system_admin_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin_replies`
--

INSERT INTO `admin_replies` (`reply_id`, `reference_type`, `reference_id`, `user_id`, `system_admin_id`, `message`, `created_at`, `read_status`) VALUES
(1, 'enquiry', 2, 1, 3, 'Hi how are you? how can i help you?', '2025-05-06 11:48:52', 1),
(2, 'enquiry', 4, 5, 3, 'You can view in bookings and services pages-if you need any sort of other help you visit our office. you can find the location details in contact us page', '2025-05-15 12:57:21', 1),
(3, 'enquiry', 5, 8, 3, 'you will get information soon ', '2025-05-16 05:09:53', 1),
(4, 'enquiry', 4, 5, 3, 'hello, will get back to you', '2025-05-18 03:24:34', 1),
(5, 'enquiry', 11, NULL, 3, 'can you say - which event you booked for?\\r\\n', '2025-05-28 05:21:11', 1),
(6, 'enquiry', 8, 1, 3, 'yes we have , do you want to get it?', '2025-05-28 06:07:13', 1),
(7, 'enquiry', 12, 6, 3, 'hello anu? how can i help you?', '2025-05-28 06:10:53', 1);

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
CREATE TABLE IF NOT EXISTS `bookings` (
  `booking_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `unreg_user_id` int DEFAULT NULL,
  `event_id` int NOT NULL,
  `booking_date` datetime NOT NULL,
  `status` varchar(50) NOT NULL,
  `ticket_quantity` int NOT NULL,
  `check_in_status` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`booking_id`),
  KEY `event_id` (`event_id`),
  KEY `user_id` (`user_id`),
  KEY `unreg_user_id` (`unreg_user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `user_id`, `unreg_user_id`, `event_id`, `booking_date`, `status`, `ticket_quantity`, `check_in_status`) VALUES
(1, NULL, 8, 4, '2025-05-11 19:43:47', 'pending', 1, 0),
(2, 3, NULL, 1, '2025-05-11 20:50:46', 'pending', 1, 0),
(3, 3, NULL, 1, '2025-05-11 21:00:19', 'confirmed', 1, 0),
(4, 3, NULL, 1, '2025-05-11 21:13:04', 'confirmed', 1, 0),
(5, 3, NULL, 6, '2025-05-11 21:20:33', 'confirmed', 1, 0),
(6, 3, NULL, 2, '2025-05-11 22:27:13', 'confirmed', 2, 0),
(7, NULL, 9, 6, '2025-05-12 13:36:27', 'confirmed', 2, 0),
(8, 6, NULL, 9, '2025-05-15 19:47:35', 'confirmed', 1, 0),
(9, NULL, 10, 2, '2025-05-15 23:00:26', 'confirmed', 2, 0),
(10, NULL, 11, 8, '2025-05-16 13:29:46', 'confirmed', 3, 0),
(11, 1, NULL, 8, '2025-05-16 13:31:51', 'confirmed', 3, 0),
(12, NULL, 12, 7, '2025-05-17 18:50:58', 'confirmed', 1, 0),
(13, NULL, 13, 7, '2025-05-17 19:40:31', 'confirmed', 2, 0),
(14, NULL, 14, 4, '2025-05-17 20:06:28', 'confirmed', 2, 0),
(15, NULL, 15, 12, '2025-05-18 13:47:16', 'confirmed', 2, 0),
(16, 3, NULL, 12, '2025-05-19 23:17:55', 'confirmed', 1, 0),
(17, NULL, 16, 12, '2025-05-20 20:00:24', 'confirmed', 1, 0),
(18, 3, NULL, 7, '2025-05-20 20:20:50', 'confirmed', 1, 0),
(19, 3, NULL, 5, '2025-05-20 20:37:58', 'confirmed', 1, 0),
(20, 3, NULL, 12, '2025-05-20 20:44:34', 'confirmed', 1, 0),
(21, 3, NULL, 12, '2025-05-20 20:49:05', 'confirmed', 1, 0),
(22, NULL, 17, 16, '2025-05-22 22:40:20', 'confirmed', 2, 0),
(23, 1, NULL, 6, '2025-05-23 12:45:43', 'confirmed', 1, 0),
(24, 1, NULL, 6, '2025-05-23 13:13:35', 'confirmed', 2, 0),
(25, 1, NULL, 6, '2025-05-23 13:51:55', 'confirmed', 3, 0),
(26, 1, NULL, 12, '2025-05-23 14:09:29', 'confirmed', 1, 0),
(27, 1, NULL, 12, '2025-05-24 14:22:42', 'confirmed', 2, 0),
(28, NULL, 18, 6, '2025-05-28 13:28:15', 'confirmed', 1, 0),
(29, 6, NULL, 8, '2025-05-28 17:00:42', 'confirmed', 1, 0),
(30, 6, NULL, 7, '2025-05-28 17:13:43', 'confirmed', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `contact_inquiries`
--

DROP TABLE IF EXISTS `contact_inquiries`;
CREATE TABLE IF NOT EXISTS `contact_inquiries` (
  `inquiry_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `phoneno` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `enquiry` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('read','unread') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'unread',
  PRIMARY KEY (`inquiry_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_inquiries`
--

INSERT INTO `contact_inquiries` (`inquiry_id`, `name`, `email`, `phoneno`, `enquiry`, `submitted_at`, `status`) VALUES
(1, 'Prajina Khadka', 'Khadkaprajina9@gmail.com', '0412335398', 'hiii', '2025-04-26 15:47:46', 'read'),
(2, 'Ganga Bahadur Neupane', 'neupaneaashish536@gmail.com', '0420760276', 'hellooo', '2025-04-26 15:48:27', 'read'),
(3, 'ashbin neupane', 'neupaneaashish535@gmail.com', '0412122222', 'about events', '2025-05-07 12:40:57', 'read'),
(4, 'Sunil thapa', 'admin1@gmail.com', '0420760276', 'hi   i have enquiry for events happening next month', '2025-05-15 12:25:28', 'read'),
(5, 'kiran dhakal', 'kiran@gmail.com', '0333333333', 'hey i want to know about - events happening next month', '2025-05-16 05:08:45', 'read'),
(6, 'ashish neupane', 'neupa@gmail.com', '0342563131', 'hello', '2025-05-18 03:21:50', 'read'),
(7, 'ashish neupane', 'neupa@gmail.com', '0342563131', 'hiii', '2025-05-18 03:26:38', 'read'),
(8, 'Ganga Bahadur Neupane', 'neupaneaashish536@gmail.com', '0420760276', 'hello, do you have extra ticket for edge band now', '2025-05-18 03:42:13', 'read'),
(9, 'anu', 'anu@gmail.com', '4444444444', 'hi admin  do you have any cruises to tasmania', '2025-05-19 11:47:37', 'read'),
(10, 'ashish neupane', 'neupa@gmail.com', '0342563131', 'hello', '2025-05-20 09:10:25', 'read'),
(11, 'ramesh neupane', 'ramesh@gmail.com', '0342563131', 'hi admin i forgot my booking number , please lookup my booking number - i need to get my ticket', '2025-05-22 12:43:00', 'read'),
(12, 'anu kumal', 'admin2@gmail.com', '0423675555', 'hii this is anu', '2025-05-28 06:09:11', 'read');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
CREATE TABLE IF NOT EXISTS `events` (
  `event_id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `venue_id` int DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by_type` enum('system_admin','venue_admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_by_id` int NOT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `booking_status` enum('open','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'open',
  PRIMARY KEY (`event_id`),
  KEY `venue_id` (`venue_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `title`, `description`, `venue_id`, `start_datetime`, `image_path`, `created_by_type`, `created_by_id`, `status`, `created_at`, `booking_status`) VALUES
(2, 'Lekali Band ', 'Going on fire Since 90\'s, We will rock it this time. Join Us !!', 1, '2025-06-20 19:00:00', 'images/lekali.jpeg', 'system_admin', 3, 'approved', '2025-05-03 11:46:05', 'open'),
(4, 'Kuma Sagar Concert', 'Best of Kuma Sagar Coming soon in June', 2, '2025-06-22 18:00:00', 'images/kumasagar.jpeg', 'venue_admin', 5, 'approved', '2025-05-03 14:23:59', 'closed'),
(5, 'Nepathya', 'Biggest Concert happening in heart of Sydney', 3, '2025-05-28 18:30:00', 'images/Nepathya.jpg', 'venue_admin', 5, 'approved', '2025-05-06 06:45:25', 'open'),
(6, 'Bollywood Nights', 'Best Night for Mother\'s day special', 3, '2025-05-28 18:30:00', 'images/bollywoodnight.jpg', 'venue_admin', 5, 'approved', '2025-05-09 01:51:43', 'open'),
(7, 'Nepal Festival', 'Rally of Nepalese community, with Cultures and traditions', 15, '2025-06-15 12:00:00', 'images/nepalfestival.jpg', 'system_admin', 3, 'approved', '2025-05-12 04:12:54', 'open'),
(8, 'Edge Band ', 'Bang Bang-- Continuous Biggest concert', 1, '2025-06-12 18:00:00', 'images/OIP.jpeg', 'system_admin', 3, 'approved', '2025-05-12 04:38:59', 'open'),
(9, 'Mantra Band ', 'Another banger , dont miss out', 3, '2025-07-13 19:00:00', 'images/mantra.jpg', 'venue_admin', 5, 'approved', '2025-05-12 04:48:03', 'open'),
(11, 'Cruise to Goldcoast', 'A dream, A Nightmare cruise to GoldCoast', 16, '2025-07-30 07:30:00', 'images/cruise.jpg', 'system_admin', 3, 'approved', '2025-05-17 12:07:43', 'open'),
(12, 'Cruise Party ', 'Biggest cruise party for Vivid Sydney', 3, '2025-06-24 18:00:00', 'images/vivid sydney.jpg', 'venue_admin', 5, 'approved', '2025-05-17 12:22:31', 'open'),
(14, 'Exhibition', 'Dinasaur Museum Exhibition, best ever experience ', 17, '2025-08-30 10:00:00', 'images/exhibition1.jpeg', 'venue_admin', 5, 'approved', '2025-05-17 12:41:48', 'open'),
(15, 'Sajjan Raj Vaidya Rocking Concert', 'Biggest concert ever showing in Melbourne this July', 18, '2025-07-26 18:30:00', 'images/sajjanconcert.jpeg', 'venue_admin', 5, 'approved', '2025-05-18 02:10:51', 'open'),
(16, '4 Day tour to Tasmania', 'Amazing, two stop tour to Tasmania. Best ever experience !!!!', 23, '2025-07-30 08:00:00', 'images/tasmania.jpg', 'venue_admin', 5, 'approved', '2025-05-22 12:16:19', 'open');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
CREATE TABLE IF NOT EXISTS `feedback` (
  `feedback_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `event_id` int DEFAULT NULL,
  `rating` int DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('read','unread') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'unread',
  `is_ignored` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`feedback_id`),
  KEY `user_id` (`user_id`),
  KEY `event_id` (`event_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `user_id`, `event_id`, `rating`, `comment`, `submitted_at`, `status`, `is_ignored`) VALUES
(1, 1, 6, 3, '', '2025-05-23 03:33:05', 'read', 0),
(4, 1, 12, NULL, NULL, '2025-05-24 04:28:03', 'read', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `venue_admin_id` int NOT NULL,
  `system_admin_id` int DEFAULT NULL,
  `sender_type` enum('venue_admin','system_admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'system_admin',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_status` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`notification_id`),
  KEY `venue_admin_id` (`venue_admin_id`),
  KEY `fk_system_admin_id` (`system_admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `venue_admin_id`, `system_admin_id`, `sender_type`, `message`, `created_at`, `read_status`) VALUES
(6, 5, 3, 'venue_admin', 'near granville , in the park will be best', '2025-05-04 02:09:11', 1),
(7, 5, 3, 'system_admin', 'we are going to organise big holi festivals in multiple location this February', '2025-05-04 11:20:07', 1),
(8, 6, 3, 'system_admin', 'we are going to organise big holi festivals in multiple location this February', '2025-05-04 11:20:07', 1),
(9, 6, 3, 'system_admin', 'We are going to organize biggest cruise party next month', '2025-05-04 11:41:11', 1),
(10, 5, 3, 'venue_admin', 'i have organsized holi festival in merrylands park ', '2025-05-04 11:42:44', 1),
(11, 6, 3, 'venue_admin', 'the venue can be gold coast', '2025-05-04 11:45:26', 1),
(12, 5, 3, 'venue_admin', 'i am going to update ticket price for kuma sagar for second round selling ', '2025-05-04 12:01:41', 1),
(13, 5, 3, 'system_admin', 'we are going to organise wedding reception at rockdale, need to find amazing venue ', '2025-05-04 12:02:50', 1),
(14, 6, 3, 'system_admin', 'we are going to organise wedding reception at rockdale, need to find amazing venue ', '2025-05-04 12:02:50', 1),
(15, 5, 3, 'system_admin', 'we are going to organise wedding reception at rockdale, need to find amazing venue ', '2025-05-04 12:02:56', 1),
(16, 6, 3, 'system_admin', 'we are going to organise wedding reception at rockdale, need to find amazing venue ', '2025-05-04 12:02:56', 1),
(17, 5, 3, 'system_admin', 'We are going organise exhibition next month', '2025-05-04 13:05:26', 1),
(18, 6, 3, 'system_admin', 'We are going organise exhibition next month', '2025-05-04 13:05:26', 1),
(19, 5, 3, 'system_admin', 'next month , we are organising vivid sydney cruise concert party ', '2025-05-06 16:37:45', 1),
(20, 6, 3, 'system_admin', 'next month , we are organising vivid sydney cruise concert party ', '2025-05-06 16:37:45', 1),
(21, 5, 3, 'system_admin', 'hey! we r hosting nepal festival event this year , we need two venues in sydney and melbourne', '2025-05-12 13:49:11', 1),
(22, 6, 3, 'system_admin', 'hey! we r hosting nepal festival event this year , we need two venues in sydney and melbourne', '2025-05-12 13:49:11', 1),
(23, 5, 3, 'system_admin', 'hey we r having big events next month, we need to organsie new venues ', '2025-05-15 22:21:02', 1),
(24, 6, 3, 'system_admin', 'hey we r having big events next month, we need to organsie new venues ', '2025-05-15 22:21:02', 1),
(25, 5, 3, 'system_admin', 'Please Ensure Venues for next month', '2025-05-15 23:51:30', 1),
(26, 6, 3, 'system_admin', 'Please Ensure Venues for next month', '2025-05-15 23:51:30', 1),
(27, 5, 3, 'system_admin', 'we are rising up!!! we need more venues', '2025-05-16 12:50:18', 1),
(28, 6, 3, 'system_admin', 'we are rising up!!! we need more venues', '2025-05-16 12:50:18', 1),
(29, 5, 3, 'venue_admin', 'i have organised venue in rockdale football ground', '2025-05-17 21:44:39', 1),
(30, 5, 3, 'system_admin', 'Event Successfully approved', '2025-05-17 22:38:39', 1),
(31, 5, 3, 'system_admin', 'Event Successfully approved', '2025-05-17 22:39:44', 1),
(32, 5, 3, 'system_admin', 'Event Successfully approved', '2025-05-17 22:42:19', 1),
(33, 5, 3, 'system_admin', 'Event \'Sajjan Raj Vaidya Rocking Concert\' Successfully approved', '2025-05-18 12:14:08', 1),
(34, 5, 3, 'system_admin', 'Event \'Sajjan Raj Vaidya Rocking Concert\' Successfully approved', '2025-05-18 12:15:19', 1),
(35, 5, 3, 'venue_admin', 'thank you\\r\\n', '2025-05-18 12:39:50', 1),
(36, 5, 3, 'venue_admin', 'hello admin, whatas the game plan for next month\\r\\n', '2025-05-18 13:25:35', 1),
(37, 5, 3, 'venue_admin', 'hello admin', '2025-05-18 15:03:26', 1),
(38, 5, 3, 'venue_admin', 'hi admin', '2025-05-18 16:03:52', 1),
(39, 5, 3, 'system_admin', 'Event \'4 Day tour to Tasmania\' Successfully approved', '2025-05-22 22:16:53', 1),
(40, 5, 3, 'system_admin', 'hello admins, we r going to host another party next month', '2025-05-28 13:03:55', 1),
(41, 6, 3, 'system_admin', 'hello admins, we r going to host another party next month', '2025-05-28 13:03:55', 1),
(42, 5, 3, 'system_admin', 'hello guys how r you all? we need extra venues for next month', '2025-05-28 16:11:30', 1),
(43, 6, 3, 'system_admin', 'hello guys how r you all? we need extra venues for next month', '2025-05-28 16:11:30', 1),
(44, 6, 3, 'system_admin', 'hello anu, we need to set up new venue in melbourne', '2025-05-28 16:11:54', 1);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `payment_date` datetime NOT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `booking_id` (`booking_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `booking_id`, `user_id`, `amount`, `payment_method`, `transaction_id`, `receipt_path`, `status`, `payment_date`) VALUES
(1, 3, 3, '40.00', 'mastercard', 'TXN_1746961219_68208343cd398', NULL, 'completed', '2025-05-11 21:00:19'),
(2, 4, 3, '40.00', 'paypal', 'TXN_1746961984_6820864045bab', 'uploads/1746961984_a2b.png', 'completed', '2025-05-11 21:13:04'),
(3, 5, 3, '25.00', 'paypal', 'TXN_1746962433_682088016d6a6', 'uploads/1746962433_birthday.jpg', 'completed', '2025-05-11 21:20:33'),
(4, 6, 3, '50.00', 'mastercard', 'TXN_1746966433_682097a1a5d29', NULL, 'completed', '2025-05-11 22:27:13'),
(5, 7, NULL, '50.00', 'visa', 'TXN_1747020987_68216cbb25964', NULL, 'completed', '2025-05-12 13:36:27'),
(6, 8, 6, '25.00', 'mastercard', 'TXN_1747302455_6825b8378d50a', NULL, 'completed', '2025-05-15 19:47:35'),
(7, 9, NULL, '50.00', 'visa', 'TXN_1747314026_6825e56aae9c4', NULL, 'completed', '2025-05-15 23:00:26'),
(8, 10, NULL, '75.00', 'mastercard', 'TXN_1747366186_6826b12a8f28e', NULL, 'completed', '2025-05-16 13:29:46'),
(9, 11, 1, '75.00', 'mastercard', 'TXN_1747366311_6826b1a795f8f', NULL, 'completed', '2025-05-16 13:31:51'),
(10, 12, NULL, '25.00', 'mastercard', 'TXN_1747471859_68284df3059bd', NULL, 'completed', '2025-05-17 18:50:59'),
(11, 13, NULL, '50.00', 'mastercard', 'TXN_1747474878_682859bebd8bc', NULL, 'completed', '2025-05-17 19:41:18'),
(12, 14, NULL, '50.00', 'mastercard', 'TXN_1747476388_68285fa45894c', NULL, 'completed', '2025-05-17 20:06:28'),
(13, 15, NULL, '50.00', 'visa', 'TXN_1747540154_682958ba17d6d', NULL, 'completed', '2025-05-18 13:49:14'),
(14, 16, 3, '25.00', 'mastercard', 'TXN_1747660675_682b2f83ceaba', NULL, 'completed', '2025-05-19 23:17:55'),
(15, 17, NULL, '25.00', 'visa', 'TXN_1747735224_682c52b81d6e0', NULL, 'completed', '2025-05-20 20:00:24'),
(16, 18, 3, '25.00', 'paypal', 'TXN_1747737400_682c5b3810a8e', 'uploads/1747736450_nepalfestival.jpg', 'completed', '2025-05-20 20:36:40'),
(17, 19, 3, '25.00', 'paypal', 'TXN_1747737478_682c5b864fd90', 'uploads/1747737478_a2b.png', 'completed', '2025-05-20 20:37:58'),
(18, 20, 3, '25.00', 'paypal', 'TXN_1747737976_682c5d78599ab', 'uploads/1747737874_birthday.jpg', 'completed', '2025-05-20 20:46:16'),
(19, 21, 3, '25.00', 'paypal', 'TXN_1747738233_682c5e793dbce', 'uploads/1747738145_birthday.jpg', 'completed', '2025-05-20 20:50:33'),
(20, 22, NULL, '1398.00', 'mastercard', 'TXN_1747917620_682f1b34c0000', NULL, 'completed', '2025-05-22 22:40:20'),
(21, 23, 1, '25.00', 'mastercard', 'TXN_1747968343_682fe157756ea', NULL, 'completed', '2025-05-23 12:45:43'),
(22, 24, 1, '50.00', 'mastercard', 'TXN_1747970015_682fe7df8d8b3', NULL, 'completed', '2025-05-23 13:13:35'),
(23, 25, 1, '75.00', 'visa', 'TXN_1747972315_682ff0db3a501', NULL, 'completed', '2025-05-23 13:51:55'),
(24, 26, 1, '25.00', 'mastercard', 'TXN_1747973369_682ff4f9861ec', NULL, 'completed', '2025-05-23 14:09:29'),
(25, 27, 1, '50.00', 'mastercard', 'TXN_1748060562_6831499277dd2', NULL, 'completed', '2025-05-24 14:22:42'),
(26, 28, NULL, '25.00', 'mastercard', 'TXN_1748402895_683682cf78b19', NULL, 'completed', '2025-05-28 13:28:15'),
(27, 29, 6, '25.00', 'mastercard', 'TXN_1748415642_6836b49ad7270', NULL, 'completed', '2025-05-28 17:00:42'),
(28, 30, 6, '25.00', 'visa', 'TXN_1748416423_6836b7a773d84', NULL, 'completed', '2025-05-28 17:13:43');

-- --------------------------------------------------------

--
-- Table structure for table `system_admins`
--

DROP TABLE IF EXISTS `system_admins`;
CREATE TABLE IF NOT EXISTS `system_admins` (
  `admin_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `system_admins`
--

INSERT INTO `system_admins` (`admin_id`, `username`, `email`, `password`, `full_name`, `created_at`) VALUES
(3, 'admin', 'neupa@gmail.com', '$2y$10$imr2gtyf.eA57zA6qzr1huf5eua07qqBFqXOe6HQ.aJiB0NNVDpH6', 'ashish neupane', '2025-05-01 16:35:10'),
(9, 'admin23', 'admin@gmail.com', '$2y$10$T7jdiCOOy9X8jlMC887j0eVppyGOZUTC3hILEkhfq3cFiIus1VIo.', 'Admin Eventhub', '2025-05-28 03:09:00');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_prices`
--

DROP TABLE IF EXISTS `ticket_prices`;
CREATE TABLE IF NOT EXISTS `ticket_prices` (
  `price_id` int NOT NULL AUTO_INCREMENT,
  `event_id` int DEFAULT NULL,
  `ticket_price` decimal(10,2) NOT NULL,
  `set_by_type` enum('system_admin','venue_admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `set_by_id` int NOT NULL,
  `set_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`price_id`),
  KEY `event_id` (`event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ticket_prices`
--

INSERT INTO `ticket_prices` (`price_id`, `event_id`, `ticket_price`, `set_by_type`, `set_by_id`, `set_at`) VALUES
(2, 2, '25.00', 'system_admin', 3, '2025-05-03 11:47:40'),
(3, 5, '35.00', 'system_admin', 3, '2025-05-28 03:26:28'),
(4, 4, '25.00', 'system_admin', 3, '2025-05-06 07:06:03'),
(5, 6, '25.00', 'system_admin', 3, '2025-05-09 02:23:05'),
(6, 7, '25.00', 'system_admin', 3, '2025-05-12 04:14:31'),
(7, 8, '25.00', 'system_admin', 3, '2025-05-12 04:39:18'),
(8, 9, '25.00', 'system_admin', 3, '2025-05-12 05:09:12'),
(10, 11, '375.00', 'system_admin', 3, '2025-05-17 12:08:31'),
(11, 12, '25.00', 'system_admin', 3, '2025-05-17 12:44:22'),
(12, 14, '55.00', 'system_admin', 3, '2025-05-17 12:44:34'),
(13, 15, '25.00', 'system_admin', 3, '2025-05-20 09:58:42'),
(14, 16, '699.00', 'system_admin', 3, '2025-05-22 12:17:55');

-- --------------------------------------------------------

--
-- Table structure for table `unregisterusers`
--

DROP TABLE IF EXISTS `unregisterusers`;
CREATE TABLE IF NOT EXISTS `unregisterusers` (
  `unreg_user_id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `address` text NOT NULL,
  PRIMARY KEY (`unreg_user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `unregisterusers`
--

INSERT INTO `unregisterusers` (`unreg_user_id`, `first_name`, `last_name`, `address`) VALUES
(1, 'ashish', 'neupane', 'homebush west'),
(2, 'ganga', 'neupane', 'homebush west'),
(3, 'ganga', 'neupane', 'homebush west'),
(4, 'ganga', 'neupane', 'homebush west'),
(5, 'ganga', 'neupane', 'homebush west'),
(6, 'ganga', 'neupane', 'homebush west'),
(7, 'ganga', 'neupane', 'homebush west'),
(8, 'ganga', 'neupane', 'homebush'),
(9, 'Prajina', 'Khadka', 'Unit 9 37-43 Eastbourne Rd, Homebush west'),
(10, 'ashish', 'neupane', 'Unit 9 37-43 Eastbourne Rd Homebush West'),
(11, 'ashok', 'pandey', 'Unit 9 37-43 Eastbourne Rd Homebush West'),
(12, 'ashok', 'jha', 'strathfield'),
(13, 'Bashy', 'Thaees', 'strathfield, strathfield'),
(14, 'habibi', 'Neupane', 'strathfield, strathfield'),
(15, 'prajina', 'khadka', 'flemington'),
(16, 'upahar', 'kc', 'flemington'),
(17, 'ramesh', 'neupane', 'parramatta'),
(18, 'jamkumar', 'jhaa', 'guildford');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_num` varchar(15) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_type` enum('system_admin','venue_admin','attendee') NOT NULL DEFAULT 'attendee',
  `notifications_viewed` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `full_name`, `phone_num`, `created_at`, `user_type`, `notifications_viewed`) VALUES
(1, 'asis12', 'neupaneaashish536@gmail.com', '$2y$10$JVy3VwgNjoNUljvroeZ5AeuMnpHIb4FcmDJul5oxwxOLTFLGe8F72', 'Ganga Neupane', '0420760276', '2025-04-25 16:31:27', 'attendee', '2025-05-28 16:10:04'),
(2, 'prajin2', 'Khadkaprajina9@gmail.com', '$2y$10$B25bXA1pF5jzsLDUChZX9ekUzRFU2xd4TX1q3GTuXxW8OkZ6YFvkm', 'Prajina Khadka', '0412335398', '2025-04-25 16:58:12', 'attendee', '2025-05-27 13:40:33'),
(3, 'admin', 'neupa@gmail.com', '$2y$10$imr2gtyf.eA57zA6qzr1huf5eua07qqBFqXOe6HQ.aJiB0NNVDpH6', 'ashish neupane', '0342563131', '2025-05-01 16:35:10', 'system_admin', '2025-05-28 15:56:16'),
(5, 'sunil12', 'admin1@gmail.com', '$2y$10$S218glnNPMBJDFQNdil2ne3naYmN.xeoGarnMFulOe01YLBigYXEG', 'Sunil thapa', '0466622223', '2025-05-03 10:33:19', 'venue_admin', '2025-05-28 15:57:43'),
(6, 'anu23', 'admin2@gmail.com', '$2y$10$am3g5IIMDmGPlg2c.pxGze4HDK5oKEerxXq3k6lQ4VUydwJZcM15.', 'anu kumal', '0423675555', '2025-05-04 01:18:55', 'venue_admin', '2025-05-28 16:08:03'),
(8, 'kiran123', 'kiran@gmail.com', '$2y$10$nNtnlGUot63Q1Z1ne.0q9ewylItjDvjTyWz12AAFIB/NPLnhYAxjC', 'kiran dhakal', '0333333333', '2025-05-16 05:07:30', 'attendee', '2025-05-16 15:08:04'),
(9, 'admin23', 'admin@gmail.com', '$2y$10$T7jdiCOOy9X8jlMC887j0eVppyGOZUTC3hILEkhfq3cFiIus1VIo.', 'Admin Eventhub', '0456788888', '2025-05-28 03:09:00', 'system_admin', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

DROP TABLE IF EXISTS `venues`;
CREATE TABLE IF NOT EXISTS `venues` (
  `venue_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `capacity` int NOT NULL,
  `venue_admin_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `latitude` decimal(9,6) DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  PRIMARY KEY (`venue_id`),
  KEY `venue_admin_id` (`venue_admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`venue_id`, `name`, `address`, `capacity`, `venue_admin_id`, `created_at`, `image_path`, `latitude`, `longitude`) VALUES
(1, 'Darlinghurst', '31 bay street, mooray park, sydney', 500, 5, '2025-05-03 10:49:15', 'images/venues/darlinghurstconcertimage.jpg', '-34.877509', '151.241784'),
(2, 'Olympic Park', '31 boulevarde, olympic park', 1200, 5, '2025-05-03 14:01:58', 'images/venues/olympic park.jpeg', NULL, NULL),
(3, 'Sydney Harbour', '35 clarence street, Wynyard', 790, 5, '2025-05-06 06:44:13', 'images/venues/sydney harbour.jpeg', NULL, NULL),
(7, 'IBIS Hotel', '35 clarence street, Wynyard', 500, 6, '2025-05-06 12:27:35', 'images/venues/ibis concert.jpg', NULL, NULL),
(15, 'Melbourne Park', 'Olympic Blvd, Melbourne Victoria 3001', 1200, 6, '2025-05-12 04:07:50', 'images/venues/melbourne park.jpg', NULL, NULL),
(16, 'Gold Coast', 'Gold Coast, Queensland', 2000, 5, '2025-05-17 12:05:24', 'images/venues/goldcoastcruise.jpg', NULL, NULL),
(17, 'Canberra', '6 Gold Creek Road, Nicholls Australian Capital Territory 2913', 500, 5, '2025-05-17 12:35:26', 'images/venues/canberra museum.jpg', NULL, NULL),
(18, 'Melbourne', '300 Dudley Street, West, Melbourne Victoria 3003', 800, 5, '2025-05-18 02:08:40', 'images/venues/festival hall.jpg', NULL, NULL),
(23, 'Tasmania', 'Tasmania', 500, 5, '2025-05-22 12:14:28', 'images/venues/tasmaniaplace.jpg', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `venue_admins`
--

DROP TABLE IF EXISTS `venue_admins`;
CREATE TABLE IF NOT EXISTS `venue_admins` (
  `venue_admin_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`venue_admin_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `venue_admins`
--

INSERT INTO `venue_admins` (`venue_admin_id`, `username`, `email`, `password`, `full_name`, `created_at`) VALUES
(5, 'sunil12', 'admin1@gmail.com', '$2y$10$I9HXJKWap4xaSySsRmC50.Nj.rlhajPqDrxhVXh0GJ9hnY0n82IrW', 'Sunil thapa', '2025-05-03 10:33:19'),
(6, 'anu23', 'admin2@gmail.com', '$2y$10$5aXEjv1urji0l9NK94kw9e2I4KYDFNZdnpVS5KViqqjy5y.bIIpgy', 'anu kumal', '2025-05-04 01:18:55');

-- --------------------------------------------------------

--
-- Table structure for table `venue_layouts`
--

DROP TABLE IF EXISTS `venue_layouts`;
CREATE TABLE IF NOT EXISTS `venue_layouts` (
  `poi_id` int NOT NULL AUTO_INCREMENT,
  `venue_id` int NOT NULL,
  `poi_name` varchar(255) NOT NULL,
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL,
  PRIMARY KEY (`poi_id`),
  KEY `venue_id` (`venue_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `venue_layouts`
--

INSERT INTO `venue_layouts` (`poi_id`, `venue_id`, `poi_name`, `latitude`, `longitude`) VALUES
(1, 1, 'Main Stage', '-37.877609', '151.241883');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`venue_id`);

--
-- Constraints for table `ticket_prices`
--
ALTER TABLE `ticket_prices`
  ADD CONSTRAINT `ticket_prices_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
