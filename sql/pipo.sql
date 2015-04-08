-- phpMyAdmin SQL Dump
-- version 4.2.10
-- http://www.phpmyadmin.net
--
-- Machine: localhost
-- Gegenereerd op: 08 apr 2015 om 13:46
-- Serverversie: 5.5.38
-- PHP-versie: 5.6.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Databank: `erfgoedenlocatie_pipo`
--

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `datasets`
--

CREATE TABLE `datasets` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `license` varchar(255) NOT NULL,
  `author` text NOT NULL,
  `website` varchar(255) NOT NULL,
  `periode` varchar(255) NOT NULL,
  `edits` text NOT NULL,
  `editor` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `fieldmappings`
--

CREATE TABLE `fieldmappings` (
`id` int(11) NOT NULL,
  `dataset_id` varchar(255) NOT NULL,
  `mapping_type` enum('property','relation','data','') NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `value_in_field` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `datasets`
--
ALTER TABLE `datasets`
 ADD PRIMARY KEY (`id`);

--
-- Indexen voor tabel `fieldmappings`
--
ALTER TABLE `fieldmappings`
 ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT voor geëxporteerde tabellen
--

--
-- AUTO_INCREMENT voor een tabel `fieldmappings`
--
ALTER TABLE `fieldmappings`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;