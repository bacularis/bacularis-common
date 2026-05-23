<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2026 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 */

namespace Bacularis\Common\Modules\Cloud\Amazon\EC2;

use Prado\Prado;

/**
 * Amazon EC2 (Amazon Elastic Compute Cloud) instance module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Instance
{
	/**
	 * Parse and prepare EC2 instance object structure.
	 *
	 * @param object $instance EC2 instance object
	 * @param null|string $empty_val_mark empty value mark
	 * @return array parsed instance object
	 */
	public static function parseObject(object $instance, ?string $empty_val_mark = null): array
	{
		$inst = [];
		$inst['architecture'] = $instance->Architecture ?? $empty_val_mark;
		$block_dev_map = $instance->BlockDeviceMappings ?? [];
		$devices = [];
		for ($i = 0; $i < count($block_dev_map); $i++) {
			$device = [];
			$device['volume_id'] = $block_dev_map[$i]->Ebs->VolumeId ?? $empty_val_mark;
			$device['delete_on_termination'] = $block_dev_map[$i]->Ebs->DeleteOnTermination ?? false;
			$device['status'] = $block_dev_map[$i]->Ebs->Status ?? $empty_val_mark;
			$device['ebs_card_index'] = $block_dev_map[$i]->Ebs->EbsCardIndex ?? $empty_val_mark;
			$device['device_name'] = $block_dev_map[$i]->DeviceName ?? $empty_val_mark;
			$devices[] = $device;
		}
		$inst['block_device_mappings'] = $devices;
		$inst['ebs_optimized'] = $instance->EbsOptimized ?? false;
		$inst['ena_support'] = $instance->EnaSupport ?? false;
		$inst['hypervisor'] = $instance->Hypervisor ?? $empty_val_mark;
		$network_interfaces = $instance->NetworkInterfaces ?? [];
		$net_ifaces = [];
		for ($i = 0; $i < count($network_interfaces); $i++) {
			$net_iface = [];
			$net_iface['public_dsn_name'] = $network_interfaces[$i]->Association->PublicDnsName ?? $empty_val_mark;
			$net_iface['public_ip'] = $network_interfaces[$i]->Association->PublicIp ?? $empty_val_mark;
			$net_iface['netstatus'] = $network_interfaces[$i]->Attachment->Status ?? $empty_val_mark;
			$net_iface['attachmentid'] = $network_interfaces[$i]->Attachment->AttachmentId ?? $empty_val_mark;
			$groups = $network_interfaces[$i]->Groups;
			$sec_groups = [];
			for ($l = 0; $l < count($groups); $l++) {
				$sec_group = [];
				$sec_group['group_id'] = $groups[$l]->GroupId ?? $empty_val_mark;
				$sec_group['group_name'] = $groups[$l]->GroupName ?? $empty_val_mark;
				$sec_groups[] = $sec_group;
			}
			$net_iface['groups'] = $sec_groups;
			$ipv6_addresses = $network_interfaces[$i]->Ipv6Addresses ?? [];
			$ipv6_addrs = [];
			for ($l = 0; $l < count($ipv6_addresses); $l++) {
				$ipv6_addr = [];
				$ipv6_addr['ipv6_address'] = $ipv6_addresses[$l]->Ipv6Address ?? $empty_val_mark;
				$ipv6_addr['is_primary_ipv6'] = $ipv6_addresses[$l]->IsPrimaryIpv6 ?? false;
				$ipv6_addrs[] = $ipv6_addr;
			}
			$net_iface['ipv6_address'] = $ipv6_addrs;
			$net_iface['mac_address'] = $network_interfaces[$i]->MacAddress ?? $empty_val_mark;
			$net_iface['network_interface_id'] = $network_interfaces[$i]->NetworkInterfaceId ?? $empty_val_mark;
			$net_iface['owner_id'] = $network_interfaces[$i]->OwnerId ?? $empty_val_mark;
			$net_iface['private_dns_name'] = $network_interfaces[$i]->PrivateDnsName ?? $empty_val_mark;
			$net_iface['private_ip_address'] = $network_interfaces[$i]->PrivateIpAddress ?? $empty_val_mark;
			$private_ip_addresses = $network_interfaces[$i]->PrivateIpAddresses ?? [];
			$priv_ip_addrs = [];
			for ($l = 0; $l < count($private_ip_addresses); $l++) {
				$priv_ip_addr = [];
				$priv_ip_addr['public_dsn_name'] = $private_ip_addresses[$l]->Association->PublicDnsName ?? $empty_val_mark;
				$priv_ip_addr['public_ip'] = $private_ip_addresses[$l]->Association->PublicIp ?? $empty_val_mark;
				$priv_ip_addr['primary'] = $private_ip_addresses[$l]->Primary ?? false;
				$priv_ip_addr['private_dsn_name'] = $private_ip_addresses[$l]->PrivateDnsName ?? $empty_val_mark;
				$priv_ip_addr['private_ip_address'] = $private_ip_addresses[$l]->PrivateIpAddress ?? $empty_val_mark;
				$priv_ip_addrs[] = $priv_ip_addr;
			}
			$net_iface['private_ip_addresses'] = $priv_ip_addrs;
			$net_iface['source_dest_check'] = $network_interfaces[$i]->SourceDestCheck ?? false;
			$net_iface['status'] = $network_interfaces[$i]->Status ?? $empty_val_mark;
			$net_iface['subnetid'] = $network_interfaces[$i]->SubnetId ?? $empty_val_mark;
			$net_iface['vpcid'] = $network_interfaces[$i]->VpcId ?? $empty_val_mark;
			$net_iface['interface_type'] = $network_interfaces[$i]->InterfaceType ?? $empty_val_mark;
			$net_ifaces[] = $net_iface;
		}
		$inst['network_interfaces'] = $net_ifaces;
		$inst['root_device_name'] = $instance->RootDeviceName ?? $empty_val_mark;
		$inst['root_device_type'] = $instance->RootDeviceType ?? $empty_val_mark;
		$security_groups = $instance->SecurityGroups ?? [];
		$sec_groups = [];
		for ($i = 0; $i < count($security_groups); $i++) {
			$sec_group = [];
			$sec_group['group_id'] = $security_groups[$i]->GroupId ?? $empty_val_mark;
			$sec_group['group_name'] = $security_groups[$i]->GroupName ?? $empty_val_mark;
			$sec_groups[] = $sec_group;
		}
		$inst['security_groups'] = $sec_groups;
		$inst['state_reason_code'] = $instance->StateReason->Code ?? $empty_val_mark;
		$inst['state_reason_message'] = $instance->StateReason->Message ?? $empty_val_mark;
		$tags = $instance->Tags ?? [];
		$tag_list = [];
		for ($i = 0; $i < count($tags); $i++) {
			$tag = [];
			$tag['Key'] = $tags[$i]->Key;
			$tag['Value'] = $tags[$i]->Value;
			$tag_list[] = $tag;
		}
		$inst['tags'] = $tag_list;
		$inst['virtualization_type'] = $instance->VirtualizationType ?? $empty_val_mark;
		$inst['cpu_options_core_count'] = $instance->CpuOptions->CoreCount ?? 0;
		$inst['cpu_options_threads_per_core'] = $instance->CpuOptions->ThreadsPerCore ?? 0;
		$inst['cpu_options_amd_sev_snp'] = $instance->CpuOptions->AmdSevSnp ?? $empty_val_mark;
		$inst['cpu_options_nested_virtualization'] = $instance->CpuOptions->NestedVirtualization ?? $empty_val_mark;
		$inst['instance_id'] = $instance->InstanceId ?? $empty_val_mark;
		$inst['image_id'] = $instance->ImageId ?? $empty_val_mark;
		$inst['current_instance_boot_mode'] = $instance->CurrentInstanceBootMode ?? $empty_val_mark;
		$inst['platform_details'] = $instance->PlatformDetails ?? $empty_val_mark;
		$inst['instance_state_code'] = $instance->State->Code ?? -1;
		$inst['instance_state_name'] = $instance->State->Name ?? $empty_val_mark;
		$inst['private_dns_name'] = $instance->PrivateDnsName ?? $empty_val_mark;
		$inst['public_dns_name'] = $instance->PublicDnsName ?? $empty_val_mark;
		$inst['key_name'] = $instance->KeyName ?? $empty_val_mark;
		$inst['instance_type'] = $instance->InstanceType ?? $empty_val_mark;
		$inst['launch_time'] = $instance->LaunchTime ?? $empty_val_mark;
		$inst['placement_availability_zone_id'] = $instance->Placement->AvailabilityZoneId ?? $empty_val_mark;
		$inst['placement_group_name'] = $instance->Placement->GroupName ?? $empty_val_mark;
		$inst['placement_patrition_number'] = $instance->Placement->PartitionNumber ?? $empty_val_mark;
		$inst['placement_host_id'] = $instance->Placement->HostId ?? $empty_val_mark;
		$inst['placement_tenancy'] = $instance->Placement->Tenancy ?? $empty_val_mark;
		$inst['placement_group_id'] = $instance->Placement->GroupId ?? $empty_val_mark;
		$inst['placement_availability_zone'] = $instance->Placement->AvailabilityZone ?? $empty_val_mark;
		$inst['subnet_id'] = $instance->SubnetId ?? $empty_val_mark;
		$inst['vpc_id'] = $instance->VpcId ?? $empty_val_mark;
		$inst['private_ip_address'] = $instance->PrivateIpAddress ?? $empty_val_mark;
		$inst['public_ip_address'] = $instance->PublicIpAddress ?? $empty_val_mark;
		$inst['tpm_support'] = $instance->TpmSupport ?? false;
		$inst['iam_instance_profile_arn'] = $instance->IamInstanceProfile->Arn ?? $empty_val_mark;
		$inst['metadata_options_http_tokens'] = $instance->MetadataOptions->HttpTokens ?? $empty_val_mark;
		$inst['metadata_options_http_put_response_hop_limit'] = $instance->MetadataOptions->HttpPutResponseHopLimit ?? $empty_val_mark;
		$inst['metadata_options_http_endpoint'] = $instance->MetadataOptions->HttpEndpoint ?? $empty_val_mark;
		$inst['metadata_options_http_protocol_ipv6'] = $instance->MetadataOptions->HttpProtocolIpv6 ?? $empty_val_mark;
		$inst['metadata_options_instance_metadata_tags'] = $instance->MetadataOptions->InstanceMetadataTags ?? $empty_val_mark;
		$inst['sriov_net_support'] = $instance->SriovNetSupport ?? $empty_val_mark;

		return $inst;
	}

	/**
	 * Get EC2 instance details.
	 *
	 * @param string $account AWS account name
	 * @param string $instance_id EC2 instance identifier
	 * @return array instance details or empty array on error
	 */
	public static function describe(string $account, string $instance_id): array
	{
		$command = [
			'ec2',
			'describe-instances',
			"--instance-ids {$instance_id}"
		];
		$app = Prado::getApplication();
		$aws_cmd = $app->getModule('aws_command');
		$result = $aws_cmd->execCommand($account, $command);

		$instance = [];
		if ($result['error'] == 0) {
			$reservation = $result['output']->Reservations[0] ?? null;
			$instance_obj = $reservation->Instances[0] ?? null;
			if ($instance_obj) {
				$instance = self::parseObject($instance_obj);
			}
		}
		return $instance;
	}

	/**
	 * Get EC2 instance attribute.
	 *
	 * @param string $account AWS account name
	 * @param string $instance_id EC2 instance identifier
	 * @param string $attribute instance attribute
	 * @return null|object instance attribute or null on error
	 */
	public static function describeAttribute(string $account, string $instance_id, string $attribute): ?object
	{
		$command = [
			'ec2',
			'describe-instance-attribute',
			"--instance-id {$instance_id}",
			"--attribute \"{$attribute}\""
		];
		$app = Prado::getApplication();
		$aws_cmd = $app->getModule('aws_command');
		$result = $aws_cmd->execCommand($account, $command);

		$attr = null;
		if ($result['error'] == 0) {
			$attr = $result['output'] ?? null;
		}
		return $attr;
	}

	/**
	 * Get EC2 instance specification string.
	 *
	 * @param string $instance_id instance identifier
	 * @param bool $exc_boot_vol decides if exclude boot volume
	 * @param array $exc_data_vol_ids excludes selected volume identifiers
	 * @return string instance specification JSON
	 */
	public static function getInstanceSpecification(string $instance_id, bool $exc_boot_vol = false, $exc_data_vol_ids = []): string
	{
		$spec = [];
		$spec['InstanceId'] = $instance_id;
		if ($exc_boot_vol) {
			$spec['ExcludeBootVolume'] = $exc_boot_vol;
		}
		if ($exc_data_vol_ids) {
			$exclude_ids = array_map('trim', $exc_data_vol_ids);
			$spec['ExcludeDataVolumeIds'] = $exclude_ids;
		}
		$instance_spec = json_encode($spec);
		return $instance_spec;
	}

	/**
	 * Get instance placement.
	 * This is useful in run instance command (ex: restore instance to AWS).
	 * Placement determines locations where the instance will be running.
	 *
	 * @param array $props placement properties
	 * @return string placement JSON
	 */
	public static function getPlacement(array $props): string
	{
		$placement = [];
		if (key_exists('availability_zone_id', $props)) {
			$placement['AvailabilityZoneId'] = $props['availability_zone_id'];
		}
		if (key_exists('availability_zone', $props)) {
			$placement['AvailabilityZone'] = $props['availability_zone'];
		}
		if (key_exists('group_id', $props)) {
			$placement['GroupId'] = $props['group_id'];
		}
		if (key_exists('group_name', $props) && !empty($props['group_name'])) {
			$placement['GroupName'] = $props['group_name'];
		}
		if (key_exists('partition_number', $props)) {
			$placement['PartitionNumber'] = $props['partition_number'];
		}
		$ret = '';
		if ($placement) {
			$ret = json_encode($placement);
		}
		return $ret;
	}

	/**
	 * Get launch template.
	 *
	 * @param array $props launch template properties
	 * @return string launch template JSON or empty string
	 */
	public static function getLaunchTemplate(array $props = []): string
	{
		$launch_template = [];
		if (key_exists('launch_template_id', $props)) {
			$launch_template['LaunchTemplateId'] = $props['launch_template_id'];
		}
		if (key_exists('launch_template_name', $props)) {
			$launch_template['LaunchTemplateName'] = $props['launch_template_name'];
		}
		if (key_exists('launch_template_version', $props)) {
			$launch_template['Version'] = $props['launch_template_version'];
		}
		$ret = '';
		if ($launch_template) {
			$ret = json_encode($launch_template);
		}
		return $ret;
	}

	/**
	 * Get instance metadata options.
	 *
	 * @param array $props instance metadata properties
	 * @return string metadata options or empty string
	 */
	public static function getMetadataOptions(array $props = []): string
	{
		$metadata_options = [];
		if (key_exists('http_tokens', $props)) {
			$metadata_options['HttpTokens'] = $props['http_tokens'];
		}
		if (key_exists('http_endpoint', $props)) {
			$metadata_options['HttpEndpoint'] = $props['http_endpoint'];
		}
		if (key_exists('http_put_response_hop_limit', $props)) {
			$metadata_options['HttpPutResponseHopLimit'] = $props['http_put_response_hop_limit'];
		}
		if (key_exists('http_protocol_ipv6', $props)) {
			$metadata_options['HttpProtocolIpv6'] = $props['http_protocol_ipv6'];
		}
		if (key_exists('instance_metadata_tags', $props)) {
			$metadata_options['InstanceMetadataTags'] = $props['instance_metadata_tags'];
		}
		$ret = '';
		if ($metadata_options) {
			$ret = json_encode($metadata_options);
		}
		return $ret;
	}

	/**
	 * Get instance CPU options.
	 *
	 * @param array $props CPU properties
	 * @return string instance CPU options
	 */
	public static function getCPUOptions(array $props = []): string
	{
		$cpu_options = [];
		if (key_exists('core_count', $props)) {
			$cpu_options['CoreCount'] = $props['core_count'];
		}
		if (key_exists('threads_per_core', $props)) {
			$cpu_options['ThreadsPerCore'] = $props['threads_per_core'];
		}
		if (key_exists('amd_sev_snp', $props)) {
			$cpu_options['AmdSevSnp'] = $props['amd_sev_snp'];
		}
		if (key_exists('nested_virtualization', $props)) {
			$cpu_options['NestedVirtualization'] = $props['nested_virtualization'];
		}
		$ret = '';
		if ($cpu_options) {
			$ret = json_encode($cpu_options);
		}
		return $ret;
	}

	/**
	 * Run EC2 instance.
	 *
	 * @param string $account AWS account name
	 * @param array $props run instance properties
	 * @return array run instance output, error code and running instance object list
	 */
	public static function runInstance(string $account, array $props): array
	{
		// Prepare command parameters
		$params = [
			'ec2',
			'run-instances'
		];
		if (key_exists('image_id', $props)) {
			$params[] = "--image-id \"{$props['image_id']}\"";
		}
		if (key_exists('instance_type', $props)) {
			$params[] = "--instance-type \"{$props['instance_type']}\"";
		}
		if (key_exists('imds_support', $props)) {
			$params[] = "--imds-support {$props['imds_support']}";
		}
		if (key_exists('placement', $props)) {
			$params[] = "--placement '{$props['placement']}'";
		}
		if (key_exists('subnet_id', $props)) {
			$params[] = "--subnet-id \"{$props['subnet_id']}\"";
		}
		if (key_exists('security_groups', $props)) {
			$params[] = "--security-groups {$props['security_groups']}";
		} elseif (key_exists('security_group_ids', $props)) {
			$params[] = "--security-group-ids {$props['security_group_ids']}";
		}
		if (key_exists('key_name', $props)) {
			$params[] = "--key-name \"{$props['key_name']}\"";
		}
		if (key_exists('launch_template', $props)) {
			$params[] = "--launch-template '{$props['launch_template']}'";
		}
		if (key_exists('private_ip_addr', $props)) {
			$params[] = "--private-ip-address \"{$props['private_ip_addr']}\"";
		}
		if (key_exists('metadata_options', $props)) {
			$params[] = "--metadata-options '{$props['metadata_options']}'";
		}
		if (key_exists('user_data', $props)) {
			$params[] = "--user-data '{$props['user_data']}'";
		}
		if (key_exists('cpu_options', $props)) {
			$params[] = "--cpu-options '{$props['cpu_options']}'";
		}
		if (key_exists('tag_specifications', $props)) {
			$params[] = "--tag-specifications '{$props['tag_specifications']}'";
		}
		if (key_exists('ebs_optimized', $props)) {
			$params[] = "--ebs-optimized";
		}
		if (key_exists('iam_instance_profile', $props)) {
			$params[] = "--iam-instance-profile '{$props['iam_instance_profile']}'";
		}

		// Run instances command
		$app = Prado::getApplication();
		$aws_cmd = $app->getModule('aws_command');
		$aws_cmd::addGlobalOptions($params);
		$result = $aws_cmd->execCommand($account, $params);
		$instance_list = [];
		if ($result['error'] == 0) {
			$instances = $result['output']->Instances ?? [];
			for ($i = 0; $i < count($instances); $i++) {
				$instance = self::parseObject($instances[$i]);

				// Let's wait until instance is ready
				self::waitOnInstanceRunning(
					$account,
					$instance['instance_id']
				);

				$instance_list[] = $instance;
			}
		}
		return [
			'instances' => $instance_list,
			'output' => $result['output'],
			'error' => $result['error']
		];
	}

	/**
	 * Wait until EC2 instance becomes running.
	 * It will poll every 15 seconds until a successful state has been reached.
	 * This will return on success or after 40 failed checks.
	 *
	 * @param string $account AWS account name
	 * @param string $instance_id instance identifier
	 * @return bool true on success, false otherwise (timeout)
	 */
	private static function waitOnInstanceRunning(string $account, string $instance_id): bool
	{
		$action = 'instance-running';
		$params = [
			"--instance-ids \"{$instance_id}\""
		];
		return Wait::waiting($account, $action, $params);
	}
}
