
--
-- Table structure for table `project_changes`
--

CREATE TABLE `project_changes` (
  `ChangeID` int(11) NOT NULL,
  `ChangedTable` varchar(100) NOT NULL,
  `ChangedColumn` varchar(100) NOT NULL,
  `ChangedIDValue` int(11) NOT NULL,
  `NewValue` varchar(1000) DEFAULT NULL,
  `OldValue` varchar(1000) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `UpdateDateTime` datetime NOT NULL,
  `IpAddress` varchar(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `project_changes`
--
ALTER TABLE `project_changes`
  ADD PRIMARY KEY (`ChangeID`);

--
-- AUTO_INCREMENT for table `project_changes`
--
ALTER TABLE `project_changes`
  MODIFY `ChangeID` int(11) NOT NULL AUTO_INCREMENT;