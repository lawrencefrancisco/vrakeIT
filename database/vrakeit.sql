-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 04, 2026 at 12:58 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vrakeit`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `is_active`, `created_at`) VALUES
(1, 'VrakeIT RA consultation', 'Wala pa kong tulog tatlong araw na', 1, '2026-05-03 22:44:32'),
(2, 'Leader namin nag vavalo', 'tumtumna na to guys', 1, '2026-05-03 22:45:54');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, NULL, 'login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 09:48:44'),
(2, NULL, 'login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 09:53:26'),
(3, NULL, 'login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 10:10:51'),
(4, NULL, 'otp_sent', 'Email: lawrence.dhaniel@gmail.com, Method: email', '::1', '2026-05-03 10:11:15'),
(5, 1, 'account_created', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 10:11:36'),
(6, 1, 'profile_updated', '', '::1', '2026-05-03 10:11:51'),
(7, 1, 'report_submitted', 'Ref: VR20260503-904CC8, Flow: second', '::1', '2026-05-03 10:13:45'),
(8, 1, 'verification_submitted', 'ID type: Passport', '::1', '2026-05-03 10:23:28'),
(9, NULL, 'merchant_registered', 'Business: Mang Juan Carwash', '::1', '2026-05-03 12:24:12'),
(10, NULL, 'merchant_login_success', 'Business: Mang Juan Carwash', '::1', '2026-05-03 12:28:54'),
(11, NULL, 'merchant_reward_added', 'Reward: FREE 20 minutes carwash by merchant #1', '::1', '2026-05-03 12:31:49'),
(12, 1, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 12:42:14'),
(13, 1, 'report_submitted', 'Ref: VR20260503-A945A2, Flow: second', '::1', '2026-05-03 12:43:06'),
(14, 1, 'logout', '', '::1', '2026-05-03 12:43:55'),
(15, 1, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 13:00:11'),
(16, 1, 'report_submitted', 'Ref: VR20260503-8C12E5, Flow: second', '::1', '2026-05-03 13:00:56'),
(17, 1, 'logout', '', '::1', '2026-05-03 13:03:11'),
(18, 1, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 13:08:51'),
(19, 1, 'logout', '', '::1', '2026-05-03 13:08:58'),
(20, NULL, 'login_failed_role', 'Email: lawrence.dhaniel@gmail.com, Expected: enforcer, Got: user', '::1', '2026-05-03 13:09:05'),
(21, NULL, 'otp_sent', 'Email: vrakeit@gmail.com, Method: email', '::1', '2026-05-03 13:10:00'),
(22, 2, 'account_created', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 13:10:22'),
(23, 2, 'report_submitted', 'Ref: VR20260503-3F0389, Flow: second', '::1', '2026-05-03 13:11:16'),
(24, 2, 'logout', '', '::1', '2026-05-03 13:11:26'),
(25, NULL, 'login_failed_role', 'Email: vrakeit@gmail.com, Expected: user, Got: enforcer', '::1', '2026-05-03 13:11:46'),
(26, 1, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 13:11:52'),
(27, 1, 'logout', '', '::1', '2026-05-03 13:11:56'),
(28, NULL, 'login_failed', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 13:20:59'),
(29, NULL, 'login_failed', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 13:21:05'),
(30, 2, 'login_success', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 13:21:42'),
(31, 2, 'logout', '', '::1', '2026-05-03 13:21:45'),
(32, 2, 'login_success', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 13:22:01'),
(33, 2, 'logout', '', '::1', '2026-05-03 13:22:05'),
(34, 1, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 13:25:04'),
(35, 1, 'good_citizen_report', 'Ref: VR20260503-C7A7C1, +50 pts', '::1', '2026-05-03 13:25:32'),
(36, 1, 'logout', '', '::1', '2026-05-03 13:26:42'),
(37, 1, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 13:34:58'),
(38, 1, 'good_citizen_report', 'Ref: VR20260503-01B3CA, +50 pts', '::1', '2026-05-03 13:47:44'),
(39, 1, 'good_citizen_report', 'Ref: VR20260503-D527B9, +50 pts', '::1', '2026-05-03 13:49:01'),
(40, 1, 'report_submitted', 'Ref: VR20260503-252FEC, Flow: second', '::1', '2026-05-03 13:49:54'),
(41, 1, 'good_citizen_report', 'Ref: VR20260503-5C5040, +50 pts', '::1', '2026-05-03 13:50:29'),
(42, 1, 'report_submitted', 'Ref: VR20260503-5B1ADC, Flow: second', '::1', '2026-05-03 13:51:01'),
(43, 1, 'good_citizen_report', 'Ref: VR20260503-CED067, +50 pts', '::1', '2026-05-03 13:54:04'),
(44, 1, 'good_citizen_report', 'Ref: VR20260503-2643E9, Pending +50 pts verification', '::1', '2026-05-03 14:01:54'),
(45, 1, 'good_citizen_report', 'Ref: VR20260503-D09529, Pending +50 pts verification', '::1', '2026-05-03 14:02:21'),
(46, 1, 'logout', '', '::1', '2026-05-03 14:05:51'),
(47, NULL, 'merchant_login_failed', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 14:08:29'),
(48, NULL, 'merchant_login_failed', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 14:08:35'),
(49, NULL, 'merchant_login_success', 'Business: Mang Juan Carwash', '::1', '2026-05-03 14:09:41'),
(50, NULL, 'merchant_reward_added', 'Reward: FREE 20 minutes carwash by merchant #1', '::1', '2026-05-03 14:10:22'),
(51, NULL, 'login_failed', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 14:11:07'),
(52, 2, 'login_success', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 14:11:13'),
(53, 2, 'good_citizen_report', 'Ref: VR20260503-81E79C, Pending +50 pts verification', '::1', '2026-05-03 14:12:40'),
(54, 2, 'logout', '', '::1', '2026-05-03 14:13:10'),
(55, NULL, 'merchant_login_success', 'Business: Mang Juan Carwash', '::1', '2026-05-03 14:25:18'),
(56, 1, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 14:40:40'),
(57, 1, 'logout', '', '::1', '2026-05-03 14:42:30'),
(58, 2, 'login_success', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 14:42:40'),
(59, 2, 'logout', '', '::1', '2026-05-03 14:44:33'),
(60, 1, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 14:44:38'),
(61, 1, 'logout', '', '::1', '2026-05-03 15:10:31'),
(62, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 15:32:52'),
(63, 2, 'logout', '', '::1', '2026-05-03 15:33:20'),
(64, NULL, 'login_failed', 'Email: salariomark123@gmail.com', '::1', '2026-05-03 15:33:28'),
(65, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 15:33:38'),
(66, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 15:34:08'),
(67, 1, 'logout', '', '::1', '2026-05-03 15:40:32'),
(68, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 15:40:39'),
(69, 2, 'logout', '', '::1', '2026-05-03 15:40:55'),
(70, NULL, 'otp_sent', 'Email: marcusaureliusrivera@gmail.com, Method: sms', '::1', '2026-05-03 15:41:33'),
(71, 4, 'account_created', 'Email: marcusaureliusrivera@gmail.com', '::1', '2026-05-03 15:42:02'),
(72, 4, 'logout', '', '::1', '2026-05-03 15:57:34'),
(73, NULL, 'otp_sent', 'Email: salariomark123@gmail.com, Method: email', '::1', '2026-05-03 15:58:09'),
(74, 5, 'account_created', 'Email: salariomark123@gmail.com', '::1', '2026-05-03 15:58:38'),
(75, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 16:02:46'),
(76, 5, 'login_success', 'Email: salariomark123@gmail.com', '::1', '2026-05-03 16:09:38'),
(77, 5, 'profile_updated', '', '::1', '2026-05-03 16:09:50'),
(78, 5, 'profile_updated', '', '::1', '2026-05-03 16:09:56'),
(79, 5, 'verification_submitted', 'ID type: PhilSys / National ID', '::1', '2026-05-03 16:18:44'),
(80, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 16:19:04'),
(81, 5, 'login_success', 'Email: salariomark123@gmail.com', '::1', '2026-05-03 16:28:28'),
(82, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 16:33:04'),
(83, 1, 'id_verification_approved', 'Verif #1', '::1', '2026-05-03 16:38:01'),
(84, 5, 'login_success', 'Email: salariomark123@gmail.com', '::1', '2026-05-03 16:38:18'),
(85, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 16:42:46'),
(86, 5, 'login_success', 'Email: salariomark123@gmail.com', '::1', '2026-05-03 16:44:57'),
(87, 5, 'good_citizen_report', 'Ref: VR20260503-F92D25, Pending +50 pts verification', '::1', '2026-05-03 16:56:47'),
(88, 5, 'report_submitted', 'Ref: VR20260503-451B01, Flow: second', '::1', '2026-05-03 16:57:40'),
(89, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 16:58:14'),
(90, 1, 'report_status_changed', 'Report #2 → reviewing', '::1', '2026-05-03 17:00:02'),
(91, 1, 'report_status_changed', 'Report #2 → closed', '::1', '2026-05-03 17:00:03'),
(92, 1, 'report_status_changed', 'Report #2 → pending', '::1', '2026-05-03 17:00:04'),
(93, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 17:01:20'),
(94, 2, 'report_submitted', 'Ref: VR20260503-3B46C9, Flow: second', '::1', '2026-05-03 17:02:11'),
(95, 2, 'report_submitted', 'Ref: VR20260503-CE98BD, Flow: second', '::1', '2026-05-03 17:09:00'),
(96, 2, 'report_submitted', 'Ref: VR20260503-CC7985, Flow: second', '::1', '2026-05-03 17:10:04'),
(97, 2, 'report_submitted', 'Ref: VR20260503-0EBA16, Flow: second', '::1', '2026-05-03 17:11:12'),
(98, 2, 'report_submitted', 'Ref: VR20260503-3A67C2, Flow: second', '::1', '2026-05-03 17:14:59'),
(99, 2, 'report_submitted', 'Ref: VR20260503-E3D9F6, Flow: second', '::1', '2026-05-03 17:15:42'),
(100, 2, 'report_submitted', 'Ref: VR20260503-DE00D6, Flow: second', '::1', '2026-05-03 17:16:45'),
(101, 2, 'good_citizen_report', 'Ref: VR20260503-30518E, Pending +50 pts verification', '::1', '2026-05-03 17:17:23'),
(102, 2, 'report_submitted', 'Ref: VR20260503-1CC608, Flow: second', '::1', '2026-05-03 17:40:17'),
(103, 2, 'good_citizen_report', 'Ref: VR20260503-FE604C, Pending +50 pts verification', '::1', '2026-05-03 17:40:47'),
(104, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 17:41:15'),
(105, 1, 'user_account_verified_toggled', 'User #4 → 0', '::1', '2026-05-03 17:42:44'),
(106, 1, 'user_account_verified_toggled', 'User #2 → 0', '::1', '2026-05-03 17:42:56'),
(107, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 17:43:05'),
(108, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 17:44:14'),
(109, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:30:57'),
(110, 2, 'verification_submitted', 'ID type: PhilSys / National ID', '::1', '2026-05-03 19:32:12'),
(111, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 19:32:29'),
(112, 1, 'id_verification_approved', 'Verif #2', '::1', '2026-05-03 19:32:57'),
(113, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:33:12'),
(114, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 19:34:33'),
(115, 1, 'user_account_verified_toggled', 'User #2 → 0', '::1', '2026-05-03 19:34:52'),
(116, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:35:10'),
(117, 2, 'logout', '', '::1', '2026-05-03 19:36:05'),
(118, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:36:15'),
(119, NULL, 'merchant_login_failed', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 19:36:28'),
(120, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:36:35'),
(121, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:36:36'),
(122, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:36:36'),
(123, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:36:37'),
(124, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:36:37'),
(125, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:36:37'),
(126, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:36:37'),
(127, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:36:37'),
(128, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:36:37'),
(129, NULL, 'merchant_login_failed', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 19:36:53'),
(130, NULL, 'merchant_login_failed', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 19:36:54'),
(131, NULL, 'merchant_login_failed', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 19:36:54'),
(132, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:37:23'),
(133, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 19:37:24'),
(134, 3, 'login_success', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 19:39:48'),
(135, 3, 'report_submitted', 'Ref: VR20260503-0B6B50, Flow: second', '::1', '2026-05-03 19:40:48'),
(136, 3, 'report_submitted', 'Ref: VR20260503-7AB112, Flow: second', '::1', '2026-05-03 19:45:11'),
(137, NULL, 'login_failed_role', 'Email: lawrence.dhaniel@gmail.com, Expected: enforcer, Got: user', '::1', '2026-05-03 19:48:13'),
(138, 3, 'login_success', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 19:48:27'),
(139, 3, 'report_submitted', 'Ref: VR20260503-FB23E8, Flow: second', '::1', '2026-05-03 19:49:03'),
(140, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 19:49:50'),
(141, 1, 'report_status_changed', 'Report #15 → reviewing', '::1', '2026-05-03 19:50:30'),
(142, 1, 'report_status_changed', 'Report #15 → closed', '::1', '2026-05-03 19:50:34'),
(143, 1, 'report_status_changed', 'Report #15 → reviewing', '::1', '2026-05-03 19:50:35'),
(144, 1, 'report_status_changed', 'Report #4 → closed', '::1', '2026-05-03 19:52:58'),
(145, 1, 'report_status_changed', 'Report #14 → closed', '::1', '2026-05-03 20:05:39'),
(146, 1, 'report_status_changed', 'Report #13 → closed', '::1', '2026-05-03 20:05:43'),
(147, 1, 'report_status_changed', 'Report #11 → closed', '::1', '2026-05-03 20:05:55'),
(148, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 20:06:17'),
(149, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 20:15:08'),
(150, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 20:48:16'),
(151, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 20:56:45'),
(152, 2, 'report_submitted', 'Ref: VR20260503-00B6F8, Flow: second', '::1', '2026-05-03 20:58:08'),
(153, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 20:58:21'),
(154, NULL, 'merchant_login_failed', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 21:04:50'),
(155, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 21:32:09'),
(156, NULL, 'merchant_registered', 'Business: Mang Juan Car wash', '::1', '2026-05-03 21:36:55'),
(157, NULL, 'merchant_registered', 'Business: Mang Juan Car wash', '::1', '2026-05-03 21:40:59'),
(158, NULL, 'merchant_registered', 'Business: Mang Juan Car wash', '::1', '2026-05-03 21:44:47'),
(159, NULL, 'merchant_registered', 'Business: Mang Juan Car wash', '::1', '2026-05-03 21:47:14'),
(160, 1, 'merchant_approved', 'Merchant #1', '::1', '2026-05-03 21:48:45'),
(161, NULL, 'merchant_login_success', 'Business: Mang Juan Car wash', '::1', '2026-05-03 21:48:58'),
(162, NULL, 'merchant_reward_added', 'Reward: FREE 20 minutes carwash by merchant #1', '::1', '2026-05-03 21:57:09'),
(163, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 21:58:02'),
(164, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 21:59:00'),
(165, 1, 'points_adjusted', 'User #2 pts=-100', '::1', '2026-05-03 21:59:44'),
(166, 1, 'points_adjusted', 'User #2 pts=-450', '::1', '2026-05-03 22:00:24'),
(167, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 22:00:37'),
(168, 2, 'reward_redeemed', 'Reward ID: 1, Code: VCH-41CDCD-HBG6', '::1', '2026-05-03 22:10:12'),
(169, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 22:10:48'),
(170, 1, 'report_status_changed', 'Report #12 → reviewing', '::1', '2026-05-03 22:11:03'),
(171, 1, 'report_status_changed', 'Report #12 → pending', '::1', '2026-05-03 22:11:11'),
(172, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 22:41:35'),
(173, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 22:44:43'),
(174, 1, 'admin_login', 'Admin login from ::1', '::1', '2026-05-03 22:45:10'),
(175, 2, 'login_success', 'Email: lawrence.dhaniel@gmail.com', '::1', '2026-05-03 22:46:11'),
(176, 2, 'logout', '', '::1', '2026-05-03 22:49:30'),
(177, 3, 'login_success', 'Email: vrakeit@gmail.com', '::1', '2026-05-03 22:49:42'),
(178, 3, 'logout', '', '::1', '2026-05-03 22:50:12');

-- --------------------------------------------------------

--
-- Table structure for table `good_citizen_transactions`
--

CREATE TABLE `good_citizen_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `type` enum('earned','spent') DEFAULT NULL,
  `points` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `good_citizen_transactions`
--

INSERT INTO `good_citizen_transactions` (`id`, `user_id`, `description`, `type`, `points`, `created_at`) VALUES
(1, 2, 'Admin point adjustment', '', 100, '2026-05-03 21:59:44'),
(2, 2, 'Admin point adjustment', '', 450, '2026-05-03 22:00:24'),
(6, 2, 'Redeemed: FREE 20 minutes carwash @ Mang Juan Car wash', 'spent', 50, '2026-05-03 22:10:12');

-- --------------------------------------------------------

--
-- Table structure for table `id_verifications`
--

CREATE TABLE `id_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_type` varchar(100) DEFAULT NULL,
  `full_name` varchar(150) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `id_file` varchar(255) DEFAULT NULL,
  `selfie_file` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `id_verifications`
--

INSERT INTO `id_verifications` (`id`, `user_id`, `status`, `created_at`, `id_type`, `full_name`, `birthdate`, `address`, `id_file`, `selfie_file`) VALUES
(1, 5, 'approved', '2026-05-03 16:18:44', 'PhilSys / National ID', 'Marke Salario', '0003-11-07', '100 barangay pitipiwpiw wiw wiw', 'verifications/id_5_1777825124.jpg', 'verifications/selfie_5_1777825124.jpg'),
(2, 2, 'approved', '2026-05-03 19:32:12', 'PhilSys / National ID', 'Lawrence Francisco', '2005-04-12', '4510 BLA PITIPIWPIW', 'verifications/id_2_1777836732.jpg', 'verifications/selfie_2_1777836732.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `merchants`
--

CREATE TABLE `merchants` (
  `id` int(11) NOT NULL,
  `business_name` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `permit` varchar(255) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `business_type` varchar(100) DEFAULT NULL,
  `business_address` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `merchants`
--

INSERT INTO `merchants` (`id`, `business_name`, `email`, `contact_number`, `password`, `permit`, `logo`, `status`, `created_at`, `business_type`, `business_address`) VALUES
(1, 'Mang Juan Car wash', 'carmel082008@gmail.com', '09354096682', '$2y$10$NSCYqp8xhLVOsfo0RxVEwuPxTAZNz60q0diuI5LY2A.WyFLKV8NbO', 'mrch_69f7c26201f200.41680280.png', 'mrch_69f7c2620250b9.08375249.jpg', 'approved', '2026-05-03 21:47:14', 'Automotive', '1234 Malacanang Palace');

-- --------------------------------------------------------

--
-- Table structure for table `merchant_ads`
--

CREATE TABLE `merchant_ads` (
  `id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `merchant_rewards`
--

CREATE TABLE `merchant_rewards` (
  `id` int(11) NOT NULL,
  `merchant_id` int(11) DEFAULT NULL,
  `reward_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `points_required` int(11) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `redeemed_count` int(11) DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `merchant_rewards`
--

INSERT INTO `merchant_rewards` (`id`, `merchant_id`, `reward_name`, `description`, `points_required`, `category`, `quantity`, `redeemed_count`, `expires_at`, `is_active`, `created_at`) VALUES
(1, 1, 'FREE 20 minutes carwash', 'hahahahahah', 50, 'General', 1, 1, '2026-05-05 23:57:09', 1, '2026-05-03 21:57:09');

-- --------------------------------------------------------

--
-- Table structure for table `otp_codes`
--

CREATE TABLE `otp_codes` (
  `id` int(11) NOT NULL,
  `email` varchar(191) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `delivery_method` enum('sms','email') DEFAULT 'sms',
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_codes`
--

INSERT INTO `otp_codes` (`id`, `email`, `otp_code`, `delivery_method`, `expires_at`, `is_used`, `created_at`) VALUES
(1, 'lawrence.dhaniel@gmail.com', '019326', 'email', '2026-05-03 10:11:36', 1, '2026-05-03 10:11:10'),
(2, 'vrakeit@gmail.com', '835880', 'email', '2026-05-03 13:10:22', 1, '2026-05-03 13:09:56'),
(3, 'marcusaureliusrivera@gmail.com', '175800', 'sms', '2026-05-03 15:42:02', 1, '2026-05-03 15:41:31'),
(4, 'salariomark123@gmail.com', '902437', 'email', '2026-05-03 15:58:38', 1, '2026-05-03 15:58:05');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `flow_type` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `is_injured` tinyint(1) DEFAULT NULL,
  `location_address` text DEFAULT NULL,
  `incident_date` date DEFAULT NULL,
  `incident_time` time DEFAULT NULL,
  `has_other_parties` tinyint(1) DEFAULT NULL,
  `weather_condition` varchar(50) DEFAULT NULL,
  `road_condition` varchar(50) DEFAULT NULL,
  `insurance_type` varchar(50) DEFAULT NULL,
  `media_urls` text DEFAULT NULL,
  `event_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `enforcer_type` varchar(50) DEFAULT NULL,
  `enforcer_documented` varchar(20) DEFAULT NULL,
  `emergency_services` text DEFAULT NULL,
  `is_safe` tinyint(1) DEFAULT NULL,
  `location_lat` decimal(10,8) DEFAULT NULL,
  `location_lng` decimal(11,8) DEFAULT NULL,
  `other_parties_present` tinyint(1) DEFAULT NULL,
  `damage_category` varchar(100) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `assigned_enforcer_id` int(11) DEFAULT NULL,
  `resolution_status` varchar(50) DEFAULT 'unresolved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `user_id`, `reference_number`, `flow_type`, `status`, `is_injured`, `location_address`, `incident_date`, `incident_time`, `has_other_parties`, `weather_condition`, `road_condition`, `insurance_type`, `media_urls`, `event_details`, `created_at`, `enforcer_type`, `enforcer_documented`, `emergency_services`, `is_safe`, `location_lat`, `location_lng`, `other_parties_present`, `damage_category`, `admin_notes`, `assigned_enforcer_id`, `resolution_status`) VALUES
(1, 5, 'VR20260503-F92D25', 'good_citizen', 'pending', 0, 'Santa Brigida Street, Arty Homes, Karuhatan, 2nd District, Valenzuela, Northern Manila District, Metro Manila, 1441, Philippines', '2026-05-03', '00:55:00', 0, '', '', '', NULL, '', '2026-05-03 16:56:47', '', '', NULL, 1, 14.68760612, 120.97629547, NULL, 'vehicle_vehicle', NULL, NULL, 'unresolved'),
(2, 5, 'VR20260503-451B01', 'second', 'pending', 0, 'Philippine Merchant Marine School, 1571, Lope de Vega Street, Barangay 335, Santa Cruz, Third District, Manila, Capital District, Metro Manila, 1003, Philippines', '2026-05-03', '00:57:00', 1, 'Clear / Sunny', 'Dry / Good', 'comprehensive', NULL, 'adadad', '2026-05-03 16:57:40', '', '', NULL, 1, 14.60660019, 120.98145262, 1, '', NULL, NULL, 'unresolved'),
(3, 2, 'VR20260503-3B46C9', 'second', 'pending', 0, 'Seaman&#039;s Quarter, 862, 864, 868, 870, Gonzalo Puyat Street, Barangay 391, Quiapo, Third District, Manila, Capital District, Metro Manila, 1001, Philippines', '2026-05-03', '01:01:00', 1, 'Cloudy', 'Dry / Good', 'unknown', NULL, 'adada', '2026-05-03 17:02:11', '', '', NULL, 1, 14.60023831, 120.98638331, 0, '', NULL, NULL, 'unresolved'),
(4, 2, 'VR20260503-CE98BD', 'second', 'closed', 0, '735, S. H. Loyola Street, Barangay 391, Quiapo, Third District, Manila, Capital District, Metro Manila, 1001, Philippines', '2026-05-03', '01:08:00', 1, 'Cloudy', 'Dry / Good', 'comprehensive', NULL, 'adad', '2026-05-03 17:09:00', '', '', NULL, 1, 14.60112191, 120.98747903, 1, '', NULL, NULL, 'unresolved'),
(5, 2, 'VR20260503-CC7985', 'second', 'pending', 0, 'Far Eastern University, Lerma Street, Barangay 464, Sampaloc, Fourth District, Manila, Capital District, Metro Manila, 1001, Philippines', '2026-05-03', '01:09:00', 1, 'Clear / Sunny', 'Dry / Good', 'unknown', NULL, 'adada', '2026-05-03 17:10:04', '', '', NULL, 1, 14.60288911, 120.98638332, 1, '', NULL, NULL, 'unresolved'),
(6, 2, 'VR20260503-0EBA16', 'second', 'pending', 0, 'Santisimo Rosario Parish Office, Padre Noval Street, Barangay 470, Sampaloc, Fourth District, Manila, Capital District, Metro Manila, 1015, Philippines', '2026-05-03', '01:10:00', 1, 'Clear / Sunny', 'Dry / Good', 'unknown', NULL, 'aaa', '2026-05-03 17:11:12', '', '', NULL, 1, 14.60907420, 120.98839212, 1, '', NULL, NULL, 'unresolved'),
(7, 2, 'VR20260503-3A67C2', 'second', 'pending', 0, 'Quiricada Street, Barangay 252, Tondo, Second District, Manila, Capital District, Metro Manila, 1003, Philippines', '2026-05-03', '01:14:00', 1, 'Clear / Sunny', 'Dry / Good', 'tpl', NULL, 'adada', '2026-05-03 17:14:59', '', '', NULL, 1, 14.61384543, 120.97725239, 1, '', NULL, NULL, 'unresolved'),
(8, 2, 'VR20260503-E3D9F6', 'second', 'pending', 0, 'San Miguel, Sixth District, Manila, Capital District, Metro Manila, 1005, Philippines', '2026-05-03', '01:15:00', 1, 'Cloudy', 'Dry / Good', 'unknown', NULL, 'adadada', '2026-05-03 17:15:42', '', '', NULL, 1, 14.59387625, 120.98839212, 1, '', NULL, NULL, 'unresolved'),
(9, 2, 'VR20260503-DE00D6', 'second', 'pending', 0, 'Vergara Street, 386, Quiapo, Third District, Manila, Capital District, Metro Manila, 1001, Philippines', '2026-05-03', '01:16:00', 1, 'Cloudy', 'Dry / Good', 'unknown', NULL, 'tttt', '2026-05-03 17:16:45', '', '', NULL, 1, 14.59635041, 120.98820950, 0, '', NULL, NULL, 'unresolved'),
(10, 2, 'VR20260503-30518E', 'good_citizen', 'pending', 0, 'J. Antonio Araneta Y Zaragoza, 1053, F. R. Hidalgo Street, Barangay 391, Quiapo, Third District, Manila, Capital District, Metro Manila, 1001, Philippines', '2026-05-03', '01:16:00', 0, '', '', '', NULL, '', '2026-05-03 17:17:23', '', '', NULL, 1, 14.59917798, 120.98729641, NULL, 'vehicle_vehicle', NULL, NULL, 'unresolved'),
(11, 2, 'VR20260503-1CC608', 'second', 'closed', 0, 'C. M. Recto Avenue, Barangay 313, Santa Cruz, Third District, Manila, Capital District, Metro Manila, 1003, Philippines', '2026-05-03', '01:39:00', 1, 'Clear / Sunny', 'Dry / Good', 'comprehensive', NULL, 'adada', '2026-05-03 17:40:17', '', '', NULL, 1, 14.60394943, 120.98090476, 1, '', NULL, NULL, 'unresolved'),
(12, 2, 'VR20260503-FE604C', 'good_citizen', 'pending', 0, 'Far Eastern University, Lerma Street, Barangay 464, Sampaloc, Fourth District, Manila, Capital District, Metro Manila, 1001, Philippines', '2026-05-03', '01:40:00', 0, '', '', '', NULL, '', '2026-05-03 17:40:47', '', '', NULL, 1, 14.60341927, 120.98565284, NULL, 'vehicle_object', NULL, NULL, 'unresolved'),
(13, 3, 'VR20260503-0B6B50', 'second', 'closed', 0, 'Garido Boarding House, 700, Progreso Street, Barangay 383, Barangay 391, Quiapo, Third District, Manila, Capital District, Metro Manila, 1001, Philippines', '2026-05-03', '03:39:00', 1, 'Clear / Sunny', 'Dry / Good', 'unknown', NULL, 'aaaa', '2026-05-03 19:40:48', '', '', NULL, 1, 14.60112192, 120.98729641, 1, '', NULL, NULL, 'unresolved'),
(14, 3, 'VR20260503-7AB112', 'second', 'closed', 0, 'F. Torres Street, Gonzalo Puyat Street, Santa Cruz, Third District, Manila, Capital District, Metro Manila, 1001, Philippines', '2026-05-03', '03:44:00', 1, 'Light Rain', 'Dry / Good', 'tpl', NULL, 'adad', '2026-05-03 19:45:11', '', '', NULL, 1, 14.60094519, 120.98072215, 1, '', NULL, NULL, 'unresolved'),
(15, 3, 'VR20260503-FB23E8', 'second', 'reviewing', 0, 'Vergara Street, 386, Quiapo, Third District, Manila, Capital District, Metro Manila, 1001, Philippines', '2026-05-03', '03:48:00', 1, 'Clear / Sunny', 'Dry / Good', 'comprehensive', NULL, 'adadadada', '2026-05-03 19:49:03', '', '', NULL, 1, 14.59652713, 120.98638332, 1, '', NULL, NULL, 'unresolved'),
(16, 2, 'VR20260503-00B6F8', 'second', 'pending', 0, 'Victoria Homes, General T. de Leon, 2nd District, Valenzuela, Northern Manila District, Metro Manila, 1442, Philippines', '2026-05-03', '04:56:00', 1, 'Clear / Sunny', 'Dry / Good', 'unknown', NULL, 'Upon u turning, i did not see the van but the van speed is so fast i didnt hve time to react', '2026-05-03 20:58:08', '', '', NULL, 1, 14.68151509, 120.99295757, 1, '', NULL, NULL, 'unresolved');

-- --------------------------------------------------------

--
-- Table structure for table `report_media`
--

CREATE TABLE `report_media` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `report_media`
--

INSERT INTO `report_media` (`id`, `report_id`, `file_name`, `file_path`, `file_type`, `created_at`) VALUES
(1, 1, '6884605.jpg', 'reports/1/media_0_1777803225.jpg', 'image', '2026-05-03 10:13:45'),
(2, 1, '582415088_1194096152177810_6976258668039213528_n.jpg', 'reports/1/media_1_1777803225.jpg', 'image', '2026-05-03 10:13:45'),
(3, 1, 'Screenshot 2025-11-23 231643.png', 'reports/1/media_2_1777803225.png', 'image', '2026-05-03 10:13:45'),
(4, 2, '6884605.jpg', 'reports/2/media_0_1777812186.jpg', 'image', '2026-05-03 12:43:06'),
(5, 2, '582483615_1203787011619577_1280584328834681373_n.jpg', 'reports/2/media_1_1777812186.jpg', 'image', '2026-05-03 12:43:06'),
(6, 3, '581819363_1543168860166211_6179840002010268019_n.jpg', 'reports/3/media_0_1777813256.jpg', 'image', '2026-05-03 13:00:56'),
(7, 3, '6884605.jpg', 'reports/3/media_1_1777813256.jpg', 'image', '2026-05-03 13:00:56'),
(8, 4, '6884605.jpg', 'reports/4/media_0_1777813875.jpg', 'image', '2026-05-03 13:11:15'),
(9, 4, 'Screenshot 2025-11-23 231643.png', 'reports/4/media_1_1777813875.png', 'image', '2026-05-03 13:11:15'),
(10, 5, 'pathrol image.webp', 'reports/5/media_0_1777814732.webp', 'image', '2026-05-03 13:25:32'),
(11, 6, '6884605.jpg', 'reports/6/media_0_1777816064.jpg', 'image', '2026-05-03 13:47:44'),
(12, 6, '582415088_1194096152177810_6976258668039213528_n.jpg', 'reports/6/media_1_1777816064.jpg', 'image', '2026-05-03 13:47:44'),
(13, 6, 'mulletcat.jpg', 'reports/6/media_2_1777816064.jpg', 'image', '2026-05-03 13:47:44'),
(14, 10, '582415088_1194096152177810_6976258668039213528_n.jpg', 'reports/10/media_0_1777816261.jpg', 'image', '2026-05-03 13:51:01'),
(15, 13, '6884605.jpg', 'reports/13/media_0_1777816941.jpg', 'image', '2026-05-03 14:02:21'),
(16, 14, '6884605.jpg', 'reports/14/media_0_1777817560.jpg', 'image', '2026-05-03 14:12:40'),
(17, 1, 'Screenshot 2025-11-23 231643.png', 'reports/1/media_0_1777827407.png', 'image', '2026-05-03 16:56:47'),
(18, 2, '581819363_1543168860166211_6179840002010268019_n.jpg', 'reports/2/media_0_1777827460.jpg', 'image', '2026-05-03 16:57:40'),
(19, 2, 'Screenshot 2025-11-23 231643.png', 'reports/2/media_1_1777827460.png', 'image', '2026-05-03 16:57:40'),
(20, 3, 'mulletcat.jpg', 'reports/3/media_0_1777827731.jpg', 'image', '2026-05-03 17:02:11'),
(21, 4, '6884605.jpg', 'reports/4/media_0_1777828140.jpg', 'image', '2026-05-03 17:09:00'),
(22, 5, '582415088_1194096152177810_6976258668039213528_n.jpg', 'reports/5/media_0_1777828204.jpg', 'image', '2026-05-03 17:10:04'),
(23, 6, 'cloud.jpg', 'reports/6/media_0_1777828272.jpg', 'image', '2026-05-03 17:11:12'),
(24, 7, 'cityhall_facade.jpg', 'reports/7/media_0_1777828499.jpg', 'image', '2026-05-03 17:14:59'),
(25, 8, 'cityhall_facade.jpg', 'reports/8/media_0_1777828542.jpg', 'image', '2026-05-03 17:15:42'),
(26, 9, '170f36d0e37b6236239acedbb14f7ea3.jpg', 'reports/9/media_0_1777828605.jpg', 'image', '2026-05-03 17:16:45'),
(27, 9, 'logo_69ca30e390007.png', 'reports/9/media_1_1777828605.png', 'image', '2026-05-03 17:16:45'),
(28, 10, 'profile_6_1777239149.jpg', 'reports/10/media_0_1777828643.jpg', 'image', '2026-05-03 17:17:23'),
(29, 10, '69d1fcc3ea2b0_uhm.png', 'reports/10/media_1_1777828643.png', 'image', '2026-05-03 17:17:23'),
(30, 11, 'profile_6_1777238874.jpg', 'reports/11/media_0_1777830017.jpg', 'image', '2026-05-03 17:40:17'),
(31, 12, '69a2fb4139ed5_V-Alert-mobile-app-1-1200x800.jpg', 'reports/12/media_0_1777830047.jpg', 'image', '2026-05-03 17:40:47'),
(32, 13, 'pathrol image.webp', 'reports/13/media_0_1777837248.webp', 'image', '2026-05-03 19:40:48'),
(33, 14, 'mulletcat.jpg', 'reports/14/media_0_1777837511.jpg', 'image', '2026-05-03 19:45:11'),
(34, 15, 'cloud.jpg', 'reports/15/media_0_1777837743.jpg', 'image', '2026-05-03 19:49:03'),
(35, 15, 'dark.png', 'reports/15/media_1_1777837743.png', 'image', '2026-05-03 19:49:03'),
(36, 16, '582415088_1194096152177810_6976258668039213528_n.jpg', 'reports/16/media_0_1777841888.jpg', 'image', '2026-05-03 20:58:08');

-- --------------------------------------------------------

--
-- Table structure for table `report_vehicles`
--

CREATE TABLE `report_vehicles` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `vehicle_type` varchar(50) NOT NULL,
  `plate_number` varchar(20) DEFAULT NULL,
  `vehicle_count` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `report_vehicles`
--

INSERT INTO `report_vehicles` (`id`, `report_id`, `vehicle_type`, `plate_number`, `vehicle_count`) VALUES
(1, 1, 'Motorcycle', 'RTE 226', 1),
(2, 2, 'Tricycle', 'RTE 222', 1),
(3, 3, 'Car', 'RTE 222', 1),
(4, 4, 'Motorcycle', 'RTE 222', 1),
(5, 8, 'Motorcycle', 'adadad', 1),
(6, 10, 'Car', 'adadad', 1),
(7, 2, 'Bus', 'ada 323', 1),
(8, 3, 'Motorcycle', 'XYR 434', 1),
(9, 4, 'Motorcycle', 'RTE 222', 1),
(10, 5, 'Car', 'RTE 222', 1),
(11, 6, 'Car', 'ada 323', 1),
(12, 7, 'Motorcycle', 'ABC 222', 1),
(13, 8, 'Motorcycle', 'RTE 222', 1),
(14, 9, 'Tricycle', 'RTE 222', 1),
(15, 11, 'Motorcycle', 'RTE 222', 1),
(16, 13, 'Motorcycle', 'RTE 222', 1),
(17, 14, 'Car', 'RTE 222', 1),
(18, 15, 'Jeep', 'BRT 111', 1),
(19, 16, 'Van', 'DED 456', 1);

-- --------------------------------------------------------

--
-- Table structure for table `rewards`
--

CREATE TABLE `rewards` (
  `id` int(11) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `business_name` varchar(100) DEFAULT NULL,
  `reward_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `points_required` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `points` int(11) DEFAULT 0,
  `account_verified` tinyint(1) DEFAULT 0,
  `role` varchar(20) DEFAULT 'user',
  `avatar` varchar(255) DEFAULT 'default.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `phone`, `password`, `points`, `account_verified`, `role`, `avatar`, `created_at`) VALUES
(1, 'Admin', 'User', 'admin@vrakeit.com', '09123456789', '$2y$10$DFf/.IB8sBQw1.rZPVCSxujGcqreCFnZ0SzAYUiE3Ig3SBDhUA/FS', 5000, 1, 'admin', 'default.png', '2026-05-03 15:36:34'),
(2, 'Lawrence', 'Francisco', 'lawrence.dhaniel@gmail.com', '09694309741', '$2y$10$c5FYnr6tKtbM7ZrMGwQmIuxuhXmCq/31H487tH3A2HyXZApQbiQMK', 23, 0, 'user', 'default.png', '2026-05-03 15:36:34'),
(3, 'VRAKE', 'IT', 'vrakeit@gmail.com', '09694309741', '$2y$10$IOewp462exDBreDYbomj5.6J1fRH5tmG.oHyWwj66EajZQaDB/ERu', 0, 1, 'enforcer', 'default.png', '2026-05-03 15:36:34'),
(4, 'Marcus', 'Rivera', 'marcusaureliusrivera@gmail.com', '09971532406', '$2y$10$Fa990d.2t30XP7yEfUqaN.nuqXjbdkis.yo5aJ/QcK8tQ6ciGU0BS', 0, 0, 'user', 'default.png', '2026-05-03 15:42:02'),
(5, 'Marke', 'Salario', 'salariomark123@gmail.com', '09694309741', '$2y$10$u9M2lvLaUpN.4UrUO3Bte.xROvMIVygoF7H61MuckRftXbHm3w5zu', 0, 1, 'user', 'avatar_5_1777824595.jpg', '2026-05-03 15:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `merchant_id` int(11) DEFAULT NULL,
  `reward_id` int(11) DEFAULT NULL,
  `voucher_code` varchar(50) NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `redeemed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `user_id`, `merchant_id`, `reward_id`, `voucher_code`, `status`, `expires_at`, `created_at`, `redeemed_at`) VALUES
(1, 2, 1, 1, 'VCH-41CDCD-HBG6', 'active', '2026-06-03 00:10:12', '2026-05-03 22:10:12', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `good_citizen_transactions`
--
ALTER TABLE `good_citizen_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `id_verifications`
--
ALTER TABLE `id_verifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `merchants`
--
ALTER TABLE `merchants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `merchant_ads`
--
ALTER TABLE `merchant_ads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `merchant_id` (`merchant_id`);

--
-- Indexes for table `merchant_rewards`
--
ALTER TABLE `merchant_rewards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `report_media`
--
ALTER TABLE `report_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `report_vehicles`
--
ALTER TABLE `report_vehicles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `rewards`
--
ALTER TABLE `rewards`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=179;

--
-- AUTO_INCREMENT for table `good_citizen_transactions`
--
ALTER TABLE `good_citizen_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `id_verifications`
--
ALTER TABLE `id_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `merchants`
--
ALTER TABLE `merchants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `merchant_ads`
--
ALTER TABLE `merchant_ads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `merchant_rewards`
--
ALTER TABLE `merchant_rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `otp_codes`
--
ALTER TABLE `otp_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `report_media`
--
ALTER TABLE `report_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `report_vehicles`
--
ALTER TABLE `report_vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `rewards`
--
ALTER TABLE `rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `merchant_ads`
--
ALTER TABLE `merchant_ads`
  ADD CONSTRAINT `merchant_ads_ibfk_1` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_media`
--
ALTER TABLE `report_media`
  ADD CONSTRAINT `report_media_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_vehicles`
--
ALTER TABLE `report_vehicles`
  ADD CONSTRAINT `report_vehicles_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
