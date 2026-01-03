-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 03, 2026 at 10:35 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dayflow`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
  `login_id` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee','pending') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `login_id`, `full_name`, `email`, `phone`, `password`, `role`, `created_at`) VALUES
(1, 'ADMIN001', 'System Admin', 'admin@dayflow.com', '1234567890', '$2y$10$Vj0g9PTy7T.YswqT68f2ZeUOpAdnC/Y3CyZ1YEHDrfJ61cXMDDOcW', 'admin', '2026-01-03 06:35:10'),
(2, 'OIHRC2025', 'Super Admin HR', 'hr@odoo.com', '1234567890', '$2y$10$Z.JVTCip3I2JHO/caUkhtOjn2IKa5fRoi5PD8EBhVOcKDuCagYhVy', 'admin', '2026-01-03 06:35:29'),
(3, 'EMPAYSH20260003', 'Ayaan Shaikh', 'ayaansh20051@gmail.com', '+919510447359', '$2y$10$kHkZ9LCFAd9bVPZwLx9y4egx3/1Q77O8RoeRJaBTO7SPFXA6V4fWe', 'employee', '2026-01-03 07:19:18'),
(4, 'EMPASSA20260004', 'AS SA', 'ayaanshaikh4950@gmail.com', '+914235356', '$2y$10$Ic6kRXd443weUI0tbzirOuF.vjhQUq7RnpPnXj7YGCsPym2B2VTCi', 'employee', '2026-01-03 08:39:12'),
(5, 'ODJODO20260005', 'John Doe', 'ash@gmail.com', '+7895643', '$2y$10$ZLz.7ka.YtPB7YNqsdmfJ.JLef1YaclbdIg5bDrapf2/DiT34GU.e', 'employee', '2026-01-03 08:45:32');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login_id` (`login_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
