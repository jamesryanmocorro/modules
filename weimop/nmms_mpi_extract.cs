using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Threading.Tasks;


using System.Net;
using System.Xml;
using System.IO;
using System.Security.Cryptography.X509Certificates;
using System.Security;
using System.Xml.Schema;


namespace IEMOP_NMMS_API_Tool
{

    public static class extract
    {
        public static int extract_mpi(string operation, string url, string certName, string password, string friendlyName, string exportResultsConf, string resultType, string marketRun, string regionName, string runTime, string commodity, string priceNode, string intervalEnd)
        {

            // ----- Parse ExportResultsConf.xml ----- //
            XmlDocument exportResultsDoc = new XmlDocument();
            exportResultsDoc.Load(exportResultsConf);

            XmlNodeList nodeList = null;
            XmlNode root = exportResultsDoc.DocumentElement;

            // Create an XmlNamespaceManager to resolve namespaces.
            NameTable nt = new NameTable();
            XmlNamespaceManager nsmgr = new XmlNamespaceManager(nt);
            nsmgr.AddNamespace("m", "https://www.siemens.com/soa/ExportResults.xsd");

            string xpath = "";

            xpath = String.Format("//m:ExportResultsType[@Name = '{0}']", resultType);
            XmlNode ExportResultsType = root.SelectSingleNode(xpath, nsmgr);

            // Get the headerNameList.
            List<string> headerNameList = new List<string>();
            xpath = "m:ResultsSummary/m:Header/m:HeaderElement/m:Name";

            try
            {
                nodeList = ExportResultsType.SelectNodes(xpath, nsmgr);
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                Console.WriteLine("ERROR: m:ResultsSummary/m:Header/m:HeaderElement/m:Name doesn't exist.");
                return (int)return_code.HEADERNAMELIST_NOT_FOUND;
            }
            foreach (XmlNode node in nodeList)
            {
                headerNameList.Add(node.InnerText);
            }

            // Get the headerDataTypeList.
            List<string> headerDataTypeList = new List<string>();
            xpath = "m:ResultsSummary/m:Header/m:HeaderElement/m:DataType";
            try
            {
                nodeList = ExportResultsType.SelectNodes(xpath, nsmgr);
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                Console.WriteLine("ERROR: m:ResultsSummary/m:Header/m:HeaderElement/m:DataType doesn't exist.");
                return (int)return_code.HEADERDATATYPELIST_NOT_FOUND;
            }
            foreach (XmlNode node in nodeList)
            {
                headerDataTypeList.Add(node.InnerText);
            }

            // Get the headerDataLengthList.
            List<int> headerDataLengthList = new List<int>();
            xpath = "m:ResultsSummary/m:Header/m:HeaderElement/m:DataLength";
            try
            {
                nodeList = ExportResultsType.SelectNodes(xpath, nsmgr);
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                Console.WriteLine("ERROR: m:ResultsSummary/m:Header/m:HeaderElement/m:DataLength doesn't exist.");
                return (int)return_code.HEADERDATALENGTHLIST_NOT_FOUND;
            }
            foreach (XmlNode node in nodeList)
            {
                headerDataLengthList.Add(int.Parse(node.InnerText));
            }

            // Get the bodyNameList.
            List<string> bodyNameList = new List<string>();
            xpath = "m:ResultsSummary/m:Body/m:Column/m:Name";
            try
            {
                nodeList = ExportResultsType.SelectNodes(xpath, nsmgr);
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                Console.WriteLine("ERROR: m:ResultsSummary/m:Body/m:Column/m:Name doesn't exist.");
                return (int)return_code.BODYNAMELIST_NOT_FOUND;
            }
            foreach (XmlNode node in nodeList)
            {
                bodyNameList.Add(node.InnerText);
            }

            // Get the bodyDataTypeList.
            List<string> bodyDataTypeList = new List<string>();
            xpath = "m:ResultsSummary/m:Body/m:Column/m:DataType";
            try
            {
                nodeList = ExportResultsType.SelectNodes(xpath, nsmgr);
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                Console.WriteLine("ERROR: m:ResultsSummary/m:Body/m:Column/m:DataType doesn't exist.");
                return (int)return_code.BODYDATATYPELIST_NOT_FOUND;
            }
            foreach (XmlNode node in nodeList)
            {
                bodyDataTypeList.Add(node.InnerText);
            }

            // Get the bodyDataLengthList.
            List<int> bodyDataLengthList = new List<int>();
            xpath = "m:ResultsSummary/m:Body/m:Column/m:DataLength";
            try
            {
                nodeList = ExportResultsType.SelectNodes(xpath, nsmgr);
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                Console.WriteLine("ERROR: m:ResultsSummary/m:Body/m:Column/m:DataLength doesn't exist.");
                return (int)return_code.BODYDATALENGTHLIST_NOT_FOUND;
            }
            foreach (XmlNode node in nodeList)
            {
                bodyDataLengthList.Add(int.Parse(node.InnerText));
            }

            // ----- End ----- //


            // NEEDS TO BE DELETED BEFORE SHIP THIS CODE.
            ServicePointManager.ServerCertificateValidationCallback += (sender, certificate, chain, sslPolicyErrors) => true;

            // Create the service reference client.
            export_service.ExportResultsServiceClient client = new export_service.ExportResultsServiceClient("ExportResultsServiceImplPort", url);

            // If either certName or password was not provided, prompt a window to input the password.
            int certSwitch = 1;
            if ((certName != "") && (password != ""))
            {
                certSwitch = 2;
            }

            X509Certificate2 cert = null;
            switch (certSwitch)
            {
                case 1:
                    Console.WriteLine("INFO: case 1");
                    X509Store store = new X509Store(StoreName.My, StoreLocation.CurrentUser);
                    store.Open(OpenFlags.ReadOnly);
                    foreach (X509Certificate2 cert2 in store.Certificates)
                    {
                        if (0 == friendlyName.CompareTo(cert2.FriendlyName))
                        {
                            cert = cert2;
                            break;
                        }
                    }
                    if (cert == null)
                    {
                        Console.WriteLine("ERROR: Certificate with specified friendly name doesn't exist.");
                        return (int)return_code.FRIENDLY_NAME_NOT_FOUND;
                    }
                    break;
                case 2:
                    Console.WriteLine("INFO: case 2");
                    SecureString passwordSS = new SecureString();

                    foreach (char ch in password)
                        passwordSS.AppendChar(ch);

                    cert = new X509Certificate2(certName, passwordSS);
                    break;
            }

            client.ClientCredentials.ClientCertificate.Certificate = cert;

            string[] separators = { "," };
            string[] marketRunArray = marketRun.Split(separators, StringSplitOptions.RemoveEmptyEntries);
            string[] regionNameArray = regionName.Split(separators, StringSplitOptions.RemoveEmptyEntries);
            string[] commodityArray = commodity.Split(separators, StringSplitOptions.RemoveEmptyEntries);
            string[] priceNodeArray = priceNode.Split(separators, StringSplitOptions.RemoveEmptyEntries);

            Console.WriteLine("INFO: Requesting...");

            // Invoke exportResults method.
            byte[] result = null;
            try
            {
                result = client.exportResults(resultType, marketRunArray, regionNameArray, runTime, commodityArray, priceNodeArray, intervalEnd);
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return (int)return_code.SERVICEREFERENCE_ERROR;
            }

            if (constants.debugEnable == true)
            {
                for (int iter = 0; iter < result.Length - 16; iter += 16)
                {
                    Console.Write(BitConverter.ToString(result, iter, 16));
                    Console.WriteLine("-");
                }
            }

            if (result == null || result.Length == 0)
            {
                Console.WriteLine("Error: Received an empty result byte array.");
                return (int)return_code.EMPTY_BYTE_ARRAY_RECEIVED;
            }

            Console.WriteLine("INFO: Results received.");

            // If the system architecture is little-endian (that is, little end first), reverse the byte array.
            if (BitConverter.IsLittleEndian)
                Array.Reverse(result);

            int typeSize = 0;
            int index = result.Length;

            //Write Filename

            DateTime datefile;

            if (runTime != "")
            {
                datefile = Convert.ToDateTime(runTime);
            }
            else
            {
                datefile = Convert.ToDateTime(intervalEnd);
            };

            string outputFileName = resultType + "_" + marketRun + "_" + datefile.ToString("yyyyMMdd_HHmm") + ".csv";
            if (marketRun == "")
            {
                outputFileName = resultType + "_" + datefile.ToString("yyyyMMdd_HHmm") + ".csv";
            };

            StreamWriter sw = null;

            try
            {
                sw = new StreamWriter(outputFileName);
            }
            catch (Exception e)
            {
                Console.WriteLine(e.Message);
                return (int)return_code.FAILED_TO_CREATE_FILE;
            }


            int NoOfRows = 0;
            if (string.Equals(headerNameList.ElementAt(0), "NoOfRows", StringComparison.OrdinalIgnoreCase) && headerDataTypeList.ElementAt(0) == "int")
            {
                typeSize = 4;
                index -= typeSize;
                NoOfRows = BitConverter.ToInt32(result, index);
                //sw.WriteLine("Number of Rows: {0}", NoOfRows);
            }

            // If NoOfRows equals zero, output the error description.
            if (NoOfRows == 0)
            {
                typeSize = 256;
                index -= typeSize;
                string stringR = System.Text.Encoding.Default.GetString(result, index, typeSize);
                //char[] stringCharArray = stringR.ToCharArray();
                //Array.Reverse(stringCharArray);
                //sw.Write(stringCharArray);
                //sw.Close();
                return (int)return_code.NO_DATA_RETRIEVED;
            }

            int NoOfColumns = 0;
            if (string.Equals(headerNameList.ElementAt(1), "NoOfColumns", StringComparison.OrdinalIgnoreCase) && headerDataTypeList.ElementAt(0) == "int")
            {
                typeSize = 4;
                index -= typeSize;
                NoOfColumns = BitConverter.ToInt32(result, index);
                //sw.WriteLine("Number of Columns: {0}", NoOfColumns);
            }


            // Print all of the column names in csv format.
            for (int i = 0; i < bodyNameList.Count(); i++)
            {
                sw.Write("{0}", bodyNameList.ElementAt(i));
                if (i != bodyNameList.Count() - 1)
                {
                    sw.Write(",");
                }
                else
                {
                    sw.Write("\n");
                }
            }

            // Print all of the rows in csv format.
            DateTime start;

            for (int i = 0; i < NoOfRows; i++)
            {
                for (int j = 0; j < NoOfColumns; j++)
                {
                    try
                    {
                        if (string.Equals(bodyDataTypeList.ElementAt(j), "long", StringComparison.OrdinalIgnoreCase))
                        {
                            typeSize = 8;
                            index -= typeSize;
                            long timeStamp = BitConverter.ToInt64(result, index);
                            //start = new DateTime(1970, 1, 1, 0, 0, 0, DateTimeKind.Local); // use this line if database is in UTC
                            start = new DateTime(1970, 1, 1, 0, 0, 0, DateTimeKind.Local); // use this line if database is in PHT
                            DateTime date = start.AddMilliseconds(timeStamp).ToLocalTime();

                            if (bodyNameList.ElementAt(j) == "TIME_INTERVAL" && (marketRun == "WAP" || marketRun == "DAP" || resultType == "TIPCLMP")) // Convert Start Time to End Time by +1 hour for WAP/DAP/TIPC
                            {
                                date = date.AddHours(1);
                            }
                            else if (bodyNameList.ElementAt(j) == "TIME_INTERVAL" && marketRun == "HAP") // Convert Start Time to End Time by +5 minutes for HAP
                            {
                                date = date.AddMinutes(5);
                            };

                            sw.Write(date.ToString());
                        }
                        else if (string.Equals(bodyDataTypeList.ElementAt(j), "string", StringComparison.OrdinalIgnoreCase))
                        {
                            typeSize = bodyDataLengthList.ElementAt(j);
                            index -= typeSize;
                            string stringR = System.Text.Encoding.Default.GetString(result, index, typeSize);
                            char[] stringCharArray = stringR.ToCharArray();
                            Array.Reverse(stringCharArray);
                            sw.Write(stringCharArray);
                        }
                        else if (string.Equals(bodyDataTypeList.ElementAt(j), "double", StringComparison.OrdinalIgnoreCase))
                        // else if (string.Equals(bodyDataTypeList.ElementAt(j), "double", StringComparison.OrdinalIgnoreCase) && bodyDataLengthList.ElementAt(j) == 8)
                        {
                            typeSize = 8;
                            index -= typeSize;
                            double doubleValue = BitConverter.ToDouble(result, index);
                            sw.Write(doubleValue);
                        }
                        else if (string.Equals(bodyDataTypeList.ElementAt(j), "int", StringComparison.OrdinalIgnoreCase))
                        // else if (string.Equals(bodyDataTypeList.ElementAt(j), "int", StringComparison.OrdinalIgnoreCase) && bodyDataLengthList.ElementAt(j) == 4)
                        {
                            typeSize = 4;
                            index -= typeSize;
                            int intValue = BitConverter.ToInt32(result, index);
                            sw.Write(intValue);
                        }
                    }
                    catch (Exception e)
                    {
                        Console.WriteLine(e.Message);
                        return (int)return_code.ERROR_TO_PARSE_BYTE_ARRAY;
                    }

                    if (j != NoOfColumns - 1)
                    {
                        sw.Write(",");
                    }
                    else
                    {
                        sw.Write("\n");
                    }
                }
            }

            sw.Write("\n");
            sw.Write("\n");
            sw.WriteLine("BY IEMOP/TOD/MIM NMMS:  " + resultType + " " + marketRun);

            sw.Close();
            Console.WriteLine("INFO: Results were saved in {0}", outputFileName);
            return (int)return_code.SUCCESS;
        }

        public static string return_textval(int return_code_int)
        {

            string textval = "";

            switch (return_code_int)
            {
                case 0:
                    textval = "SUCCESS";
                    break;
                case 1:
                    textval = "INVALID_ARGUMENT";
                    break;
                case 2:
                    textval = "FRIENDLY_NAME_NOT_FOUND";
                    break;
                case 3:
                    textval = "FAILED_TO_LOAD_XML_FILE";
                    break;
                case 4:
                    textval = "EMPTY_BYTE_ARRAY_RECEIVED";
                    break;
                case 5:
                    textval = "NO_DATA_RETRIEVED";
                    break;
                case 6:
                    textval = "SERVICEREFERENCE_ERROR";
                    break;
                case 7:
                    textval = "FAILED_TO_CREATE_FILE";
                    break;
                case 8:
                    textval = "ERROR_TO_PARSE_BYTE_ARRAY";
                    break;
                case 9:
                    textval = "HEADERNAMELIST_NOT_FOUND";
                    break;
                case 10:
                    textval = "HEADERDATATYPELIST_NOT_FOUND";
                    break;
                case 11:
                    textval = "HEADERDATALENGTHLIST_NOT_FOUND";
                    break;
                case 12:
                    textval = "BODYNAMELIST_NOT_FOUND";
                    break;
                case 13:
                    textval = "BODYDATATYPELIST_NOT_FOUND";
                    break;
                case 14:
                    textval = "BODYDATALENGTHLIST_NOT_FOUND";
                    break;
                case 100:
                    textval = "ERROR";
                    break;
            }

            return textval;

        }

    }

    enum return_code : int
    {
        SUCCESS = 0,
        INVALID_ARGUMENT = 1,
        FRIENDLY_NAME_NOT_FOUND = 2,
        FAILED_TO_LOAD_XML_FILE = 3,
        EMPTY_BYTE_ARRAY_RECEIVED = 4,
        NO_DATA_RETRIEVED = 5,
        SERVICEREFERENCE_ERROR = 6,
        FAILED_TO_CREATE_FILE = 7,
        ERROR_TO_PARSE_BYTE_ARRAY = 8,
        HEADERNAMELIST_NOT_FOUND = 9,
        HEADERDATATYPELIST_NOT_FOUND = 10,
        HEADERDATALENGTHLIST_NOT_FOUND = 11,
        BODYNAMELIST_NOT_FOUND = 12,
        BODYDATATYPELIST_NOT_FOUND = 13,
        BODYDATALENGTHLIST_NOT_FOUND = 14,


        ERROR = 100
    }

}
