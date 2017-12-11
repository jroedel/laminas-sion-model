
--
-- Table structure for table `mailings`
--

CREATE TABLE `project_mailings` (
  `MailingId` int(11) NOT NULL,
  `ToAddresses` varchar(2000) NOT NULL,
  `MailingOn` datetime DEFAULT NULL,
  `MailingBy` int(11) DEFAULT NULL,
  `Subject` varchar(255) DEFAULT NULL,
  `Body` text,
  `Sender` varchar(255) DEFAULT NULL,
  `MailingText` text,
  `MailingTags` varchar(255) DEFAULT NULL,
  `TrackingToken` varchar(100) DEFAULT NULL,
  `OpenedFromIpAddress` varchar(100) DEFAULT NULL,
  `OpenedFromHeaders` varchar(255) DEFAULT NULL COMMENT 'JSON',
  `OpenedOn` datetime DEFAULT NULL,
  `EmailTemplate` varchar(100) DEFAULT NULL,
  `EmailLocale` varchar(50) DEFAULT NULL,
  `Status` varchar(50) NOT NULL,
  `QueueUntil` datetime DEFAULT NULL,
  `Attempt` int(11) NOT NULL DEFAULT '1',
  `MaxAttempts` int(11) NOT NULL DEFAULT '3',
  `ErrorMessage` varchar(255) DEFAULT NULL,
  `StackTrace` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `a_data_mailing`
--
ALTER TABLE `project_mailings`
  ADD PRIMARY KEY (`MailingId`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `a_data_mailing`
--
ALTER TABLE `project_mailings`
  MODIFY `MailingId` int(11) NOT NULL AUTO_INCREMENT;