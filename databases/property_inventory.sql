-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 13, 2026 at 04:48 PM
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
-- Database: `property_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `property_name` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `serial_no` varchar(100) DEFAULT NULL,
  `date_acquired` date DEFAULT NULL,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `property_name`, `category`, `serial_no`, `date_acquired`, `remarks`) VALUES
(1, 'chair', 'furniture', '0000012', '2025-12-03', 'all chairs are in good condition'),
(2, 'computer', '', '', '2026-04-09', ''),
(3, 'headset', '', '', '2026-04-21', '');

-- --------------------------------------------------------

--
-- Table structure for table `property_assignments`
--

CREATE TABLE `property_assignments` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_assignments`
--

INSERT INTO `property_assignments` (`id`, `item_id`, `room_id`, `quantity`) VALUES
(1, 2, 1, 25),
(2, 1, 3, 25),
(3, 3, 2, 25),
(4, 1, 4, 25);

-- --------------------------------------------------------

--
-- Table structure for table `property_conditions`
--

CREATE TABLE `property_conditions` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `conditions` enum('good','damaged','missing') NOT NULL,
  `notes` text DEFAULT NULL,
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_conditions`
--

INSERT INTO `property_conditions` (`id`, `property_id`, `instructor_id`, `conditions`, `notes`, `reported_at`) VALUES
(1, 1, 4, 'good', '', '2026-04-21 01:24:16'),
(2, 1, 4, 'damaged', '', '2026-04-21 01:24:21'),
(3, 1, 4, 'missing', '', '2026-04-21 01:24:25');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `status` enum('pending','reviewed') DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `instructor_id`, `room_id`, `title`, `status`, `submitted_at`) VALUES
(1, 4, 1, 'INVENTORY REPORT AS OF 2026', 'reviewed', '2026-04-21 01:30:49');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_name`, `location`, `description`) VALUES
(1, 'comlab', '2nd floor', ''),
(2, 'speech lab', '2nd floor', ''),
(3, 'ROOM 210', '3rd Floor', ''),
(4, 'project byte', '3rd Floor', '');

-- --------------------------------------------------------

--
-- Table structure for table `room_assignments`
--

CREATE TABLE `room_assignments` (
  `id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `assigned_date` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_assignments`
--

INSERT INTO `room_assignments` (`id`, `instructor_id`, `room_id`, `assigned_date`) VALUES
(1, 4, 1, '2026-04-16'),
(2, 5, 2, '2026-04-16'),
(4, 8, 3, '2026-04-21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','instructor') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `created_at`) VALUES
(4, 'grace', 'gracebautista066@gmail.com', '$2y$12$gv7nbRCwpj5ZFqutmtBRcebkouLotXDe8hHSJUAlbhw.Ly7YvQvSS', 'instructor', '2026-04-16 01:29:12'),
(5, 'ninamae', 'ninamae@gmail.com', '$2y$12$zikcNQy5VMmhph5cpGmFS.Kb2RrvEpyG3BBEZPGnDeDbbHvx7D.wa', 'instructor', '2026-04-16 01:36:57'),
(7, 'admin', 'admin@gmail.com', '$2y$12$xBXdPLjkesAbDRY.9HOkn.O8IFtQ2fSu0bieIsWEogy1.nj.foUA6', 'admin', '2026-04-21 00:41:51'),
(8, 'frenses', 'frenses@gmail.com', '$2y$12$R1X/MHUhiiDcycFJQwqAl.Pm4NXFvMi3lrq1KEhhO5MixdzBDFhn6', 'instructor', '2026-04-21 01:42:09');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `property_assignments`
--
ALTER TABLE `property_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `property_conditions`
--
ALTER TABLE `property_conditions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `instructor_id` (`instructor_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instructor_id` (`instructor_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `room_assignments`
--
ALTER TABLE `room_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instructor_id` (`instructor_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `property_assignments`
--
ALTER TABLE `property_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `property_conditions`
--
ALTER TABLE `property_conditions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `room_assignments`
--
ALTER TABLE `room_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `property_assignments`
--
ALTER TABLE `property_assignments`
  ADD CONSTRAINT `property_assignments_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `property_assignments_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_conditions`
--
ALTER TABLE `property_conditions`
  ADD CONSTRAINT `property_conditions_ibfk_2` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`);

--
-- Constraints for table `room_assignments`
--
ALTER TABLE `room_assignments`
  ADD CONSTRAINT `room_assignments_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `room_assignments_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
